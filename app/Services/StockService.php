<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\InventoryStock;
use App\Models\StockMovement;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class StockService
{
    private const ATTEMPTS = 3; // retry a transaction if MySQL reports a deadlock

    public function setOpening(int $productId, int $warehouseId, string|int $quantity, int $userId, ?string $note = null): StockMovement
    {
        $qty = $this->positive($quantity);

        return DB::transaction(function () use ($productId, $warehouseId, $qty, $userId, $note) {
            $this->lockStock($productId, $warehouseId);

            $alreadyEntered = StockMovement::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->where('movement_type', StockMovement::TYPE_OPENING)
                ->exists();

            if ($alreadyEntered) {
                throw new DomainException('Opening stock was already entered for this product and warehouse.');
            }

            return $this->apply($productId, $warehouseId, $qty, StockMovement::TYPE_OPENING, $userId, $note);
        }, self::ATTEMPTS);
    }

    public function receive(int $productId, int $warehouseId, string|int $quantity, int $userId, ?int $goodsReceiptItemId = null): StockMovement
    {
        $qty = $this->positive($quantity);

        return DB::transaction(function () use ($productId, $warehouseId, $qty, $userId, $goodsReceiptItemId) {
            $this->lockStock($productId, $warehouseId);
            $this->assertNotRecorded(StockMovement::TYPE_RECEIPT, 'goods_receipt_item_id', $goodsReceiptItemId, 'This goods receipt line was already received into stock.');

            return $this->apply($productId, $warehouseId, $qty, StockMovement::TYPE_RECEIPT, $userId, null, $goodsReceiptItemId);
        }, self::ATTEMPTS);
    }

    public function deduct(int $productId, int $warehouseId, string|int $quantity, int $userId, ?int $saleItemId = null): StockMovement
    {
        $qty = $this->positive($quantity);

        return DB::transaction(function () use ($productId, $warehouseId, $qty, $userId, $saleItemId) {
            $this->lockStock($productId, $warehouseId);
            $this->assertNotRecorded(StockMovement::TYPE_SALE, 'sale_item_id', $saleItemId, 'Stock was already deducted for this sale line.');

            return $this->apply($productId, $warehouseId, $qty->negated(), StockMovement::TYPE_SALE, $userId, null, null, $saleItemId);
        }, self::ATTEMPTS);
    }

    public function reverse(int $productId, int $warehouseId, string|int $quantity, int $userId, ?int $saleItemId = null): StockMovement
    {
        $qty = $this->positive($quantity);

        return DB::transaction(function () use ($productId, $warehouseId, $qty, $userId, $saleItemId) {
            $this->lockStock($productId, $warehouseId);
            $this->assertNotRecorded(StockMovement::TYPE_SALE_REVERSAL, 'sale_item_id', $saleItemId, 'This sale line was already reversed.');

            return $this->apply($productId, $warehouseId, $qty, StockMovement::TYPE_SALE_REVERSAL, $userId, null, null, $saleItemId);
        }, self::ATTEMPTS);
    }

    public function adjust(int $productId, int $warehouseId, string|int $signedQuantity, string $note, int $userId): StockMovement
    {
        if (trim($note) === '') {
            throw new InvalidArgumentException('A note is required for a stock adjustment.');
        }

        $change = $this->decimal($signedQuantity);

        if ($change->isZero()) {
            throw new InvalidArgumentException('An adjustment cannot be zero.');
        }

        return DB::transaction(function () use ($productId, $warehouseId, $change, $note, $userId) {
            return $this->apply($productId, $warehouseId, $change, StockMovement::TYPE_ADJUSTMENT, $userId, trim($note));
        }, self::ATTEMPTS);
    }

    /**
     * @return array{out: StockMovement, in: StockMovement}
     */
    public function transfer(int $productId, int $fromWarehouseId, int $toWarehouseId, string|int $quantity, int $userId, ?string $note = null): array
    {
        $qty = $this->positive($quantity);

        if ($fromWarehouseId === $toWarehouseId) {
            throw new InvalidArgumentException('Source and destination warehouses must be different.');
        }

        return DB::transaction(function () use ($productId, $fromWarehouseId, $toWarehouseId, $qty, $userId, $note) {
            // Always lock the lower warehouse id first, so two opposite transfers cannot deadlock.
            foreach ([min($fromWarehouseId, $toWarehouseId), max($fromWarehouseId, $toWarehouseId)] as $warehouseId) {
                $this->lockStock($productId, $warehouseId);
            }

            $reference = 'TRF-' . strtoupper(Str::random(10));
            $text = trim("Transfer {$reference}. " . ($note ?? ''));

            $out = $this->apply($productId, $fromWarehouseId, $qty->negated(), StockMovement::TYPE_TRANSFER_OUT, $userId, $text);
            $in = $this->apply($productId, $toWarehouseId, $qty, StockMovement::TYPE_TRANSFER_IN, $userId, $text);

            return ['out' => $out, 'in' => $in];
        }, self::ATTEMPTS);
    }

    /**
     * The one place where stock changes: lock, check, update balance, write ledger line.
     */
    private function apply(
        int $productId,
        int $warehouseId,
        BigDecimal $change,
        string $type,
        int $userId,
        ?string $note = null,
        ?int $goodsReceiptItemId = null,
        ?int $saleItemId = null,
    ): StockMovement {
        $stock = $this->lockStock($productId, $warehouseId);
        $current = BigDecimal::of($stock->quantity)->toScale(3);
        $new = $current->plus($change)->toScale(3);

        if ($new->isNegative()) {
            throw new InsufficientStockException((string) $current, (string) $change->abs()->toScale(3));
        }

        $stock->quantity = (string) $new;
        $stock->save();

        return StockMovement::create([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'movement_type' => $type,
            'quantity' => (string) $change,
            'goods_receipt_item_id' => $goodsReceiptItemId,
            'sale_item_id' => $saleItemId,
            'performed_by' => $userId,
            'note' => $note,
        ]);
    }

    /**
     * Returns the stock row locked for this transaction, creating it at 0 if it does not exist yet.
     */
    private function lockStock(int $productId, int $warehouseId): InventoryStock
    {
        $find = fn() => InventoryStock::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate();

        $stock = $find()->first();

        if (! $stock) {
            InventoryStock::query()->insertOrIgnore([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'quantity' => '0.000',
                'updated_at' => now(),
            ]);

            $stock = $find()->firstOrFail();
        }

        return $stock;
    }

    private function assertNotRecorded(string $type, string $column, ?int $documentId, string $message): void
    {
        if ($documentId === null) {
            return;
        }

        if (StockMovement::where('movement_type', $type)->where($column, $documentId)->exists()) {
            throw new DomainException($message);
        }
    }

    private function decimal(string|int $value): BigDecimal
    {
        try {
            return BigDecimal::of((string) $value)->toScale(3);
        } catch (MathException) {
            throw new InvalidArgumentException('Quantity must be a number with at most 3 decimals.');
        }
    }

    private function positive(string|int $value): BigDecimal
    {
        $decimal = $this->decimal($value);

        if (! $decimal->isPositive()) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        return $decimal;
    }
}
