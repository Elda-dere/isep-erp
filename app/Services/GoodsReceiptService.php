<?php

namespace App\Services;

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\Warehouse;
use App\Support\DocumentNumber;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Owns receiving goods against purchase orders.
 *
 * Stock changes go ONLY through StockService, and order status changes ONLY through
 * PurchaseOrderService. This class decides WHEN to call them and checks the receiving rules.
 *
 * Each line is an array like: ['purchase_order_item_id' => 12, 'quantity' => '4'].
 * Quantities are passed as strings, never floats.
 *
 * Two kinds of error: InvalidArgumentException for bad input (a quantity of 0, a line of another
 * order), DomainException when a business rule refuses (over-receipt, a wrong status).
 */
class GoodsReceiptService
{
    private const NUMBER_ATTEMPTS = 5;                // retries if two people get the same number at once
    private const POST_ATTEMPTS = 3;                  // retries if MySQL reports a deadlock
    private const BUSINESS_TIMEZONE = 'Africa/Addis_Ababa';

    public function __construct(
        private StockService $stock,
        private PurchaseOrderService $orders,
    ) {}

    // PRC-06: a draft receipt against a placed order. A draft does not touch the stock.
    public function createDraft(PurchaseOrder $order, array $lines, int $userId): GoodsReceipt
    {
        // Check the shape of the input first, so a bad line never leaves a half-built receipt behind.
        $rows = $this->normalizeLines($lines);

        return DB::transaction(function () use ($order, $rows, $userId) {
            // Re-read the order: the copy we were given may be out of date.
            $order = PurchaseOrder::findOrFail($order->id);

            $this->assertOrderReceivable($order);
            $rows = $this->assertWithinOutstanding($order, $rows);

            $receipt = $this->createWithNumber($order, $userId);
            $this->writeLines($receipt, $rows);

            AuditLogger::log(
                'goods_receipt.created',
                'goods_receipt',
                $receipt->id,
                null,
                $this->snapshot($receipt) + ['receipt_number' => $receipt->receipt_number],
            );

            return $receipt->load('items');
        });
    }

    // A draft can be changed (PRC-09 only forbids editing a POSTED receipt).
    public function updateDraft(GoodsReceipt $receipt, array $lines, int $userId): GoodsReceipt
    {
        $rows = $this->normalizeLines($lines);

        return DB::transaction(function () use ($receipt, $rows) {
            $receipt = $this->lock($receipt);

            if ($receipt->status !== GoodsReceipt::STATUS_DRAFT) {
                throw new DomainException('Only a draft receipt can be edited. A posted receipt is corrected with a stock adjustment.');
            }

            $order = PurchaseOrder::findOrFail($receipt->purchase_order_id);
            $this->assertOrderReceivable($order);
            $rows = $this->assertWithinOutstanding($order, $rows);

            $before = $this->snapshot($receipt);

            $receipt->items()->delete(); // a draft has changed no stock, so replacing its lines is safe
            $this->writeLines($receipt, $rows);

            $after = $this->snapshot($receipt);

            if ($before !== $after) {
                AuditLogger::log('goods_receipt.updated', 'goods_receipt', $receipt->id, $before, $after);
            }

            return $receipt->load('items');
        });
    }

    // PRC-09: posting. In ONE transaction: stock goes up (one movement per line), the receipt becomes
    // posted, and the order becomes partially_received or received. If anything fails, nothing is saved.
    public function post(GoodsReceipt $receipt, int $warehouseId, int $userId): GoodsReceipt
    {
        return DB::transaction(function () use ($receipt, $warehouseId, $userId) {
            $receipt = $this->lock($receipt);

            if ($receipt->status !== GoodsReceipt::STATUS_DRAFT) {
                throw new DomainException("Only a draft receipt can be posted (this one is {$receipt->status}).");
            }

            // Lock the ORDER too, so two receipts for the same order are posted one after the other.
            $order = PurchaseOrder::whereKey($receipt->purchase_order_id)->lockForUpdate()->firstOrFail();
            $this->assertOrderReceivable($order);

            // PRC-08: the warehouse is chosen when posting, and lives only on the stock movements.
            $warehouse = Warehouse::find($warehouseId);

            if (! $warehouse) {
                throw new InvalidArgumentException('The warehouse does not exist.');
            }

            // Product order, so two postings always take their stock locks in the same order (no deadlock).
            $items = $receipt->items()->reorder()->orderBy('product_id')->orderBy('id')->get();

            if ($items->isEmpty()) {
                throw new DomainException('A receipt without lines cannot be posted.');
            }

            // Check again NOW: since this draft was written, another receipt may have been posted.
            $this->assertWithinOutstanding($order, $items->map(fn($item) => [
                'purchase_order_item_id' => (int) $item->purchase_order_item_id,
                'quantity' => BigDecimal::of((string) $item->quantity_received)->toScale(3),
            ])->all());

            // The stock goes up, ONLY through StockService. Passing the receipt line's id lets it refuse
            // to book the same line twice.
            foreach ($items as $item) {
                $this->stock->receive(
                    (int) $item->product_id,
                    $warehouse->id,
                    (string) $item->quantity_received,
                    $userId,
                    (int) $item->id,
                );
            }

            $receipt->update(['status' => GoodsReceipt::STATUS_POSTED]);

            // The receipt is posted now, so it counts when the order recalculates its status.
            $this->orders->refreshReceivingStatus($order);

            AuditLogger::log(
                'goods_receipt.posted',
                'goods_receipt',
                $receipt->id,
                ['status' => GoodsReceipt::STATUS_DRAFT],
                ['status' => GoodsReceipt::STATUS_POSTED, 'warehouse_id' => $warehouse->id] + $this->snapshot($receipt),
            );

            return $receipt->load('items');
        }, self::POST_ATTEMPTS);
    }

    // Only a draft can be cancelled: a posted receipt has already moved stock.
    public function cancel(GoodsReceipt $receipt, int $userId): GoodsReceipt
    {
        return DB::transaction(function () use ($receipt) {
            $receipt = $this->lock($receipt);

            if ($receipt->status !== GoodsReceipt::STATUS_DRAFT) {
                throw new DomainException('Only a draft receipt can be cancelled. A posted receipt is corrected with a stock adjustment.');
            }

            $receipt->update(['status' => GoodsReceipt::STATUS_CANCELLED]);

            AuditLogger::log(
                'goods_receipt.cancelled',
                'goods_receipt',
                $receipt->id,
                ['status' => GoodsReceipt::STATUS_DRAFT],
                ['status' => GoodsReceipt::STATUS_CANCELLED],
            );

            return $receipt;
        });
    }

    // Re-reads the receipt with a lock, so two people cannot change the same receipt at the same moment.
    private function lock(GoodsReceipt $receipt): GoodsReceipt
    {
        return GoodsReceipt::whereKey($receipt->id)->lockForUpdate()->firstOrFail();
    }

    // Goods can be received only against an order that has been placed and is not finished.
    private function assertOrderReceivable(PurchaseOrder $order): void
    {
        $allowed = [PurchaseOrder::STATUS_ORDERED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED];

        if (! in_array($order->status, $allowed, true)) {
            throw new DomainException("Goods can be received only against a placed order (this one is {$order->status}).");
        }
    }

    // PRC-07: for every line, the quantity cannot exceed ordered minus already received.
    // "Already received" counts POSTED receipts only. Returns the rows with the product id added.
    private function assertWithinOutstanding(PurchaseOrder $order, array $rows): array
    {
        $items = $order->items()->with('product:id,sku')->get()->keyBy('id');
        $received = $this->orders->receivedQuantities($order);

        $result = [];

        foreach ($rows as $index => $row) {
            $number = $index + 1;
            $item = $items->get($row['purchase_order_item_id']);

            if (! $item) {
                throw new InvalidArgumentException("Line {$number}: this line does not belong to the order.");
            }

            $ordered = BigDecimal::of((string) $item->quantity)->toScale(3);
            $already = $received[$item->id] ?? BigDecimal::zero()->toScale(3);
            $outstanding = $ordered->minus($already);

            if ($row['quantity']->isGreaterThan($outstanding)) {
                // Like the stock errors, the message says how much IS available.
                throw new DomainException(
                    "Line {$number}: product {$item->product->sku}: only {$outstanding} is still outstanding (ordered {$ordered}, received {$already})."
                );
            }

            $result[] = $row + ['product_id' => (int) $item->product_id];
        }

        return $result;
    }

    // Creates the receipt row with the next number. If two people ask for the same number at the
    // same time, the database's unique index refuses one of them and that one simply tries again.
    private function createWithNumber(PurchaseOrder $order, int $userId): GoodsReceipt
    {
        // The year is the business year in Addis Ababa, not UTC.
        $prefix = 'GRN-' . now(self::BUSINESS_TIMEZONE)->year . '-';

        for ($attempt = 1;; $attempt++) {
            try {
                // A nested transaction (a savepoint): a failed insert leaves the outer transaction usable.
                return DB::transaction(fn() => GoodsReceipt::create([
                    'purchase_order_id' => $order->id,
                    'receipt_number' => DocumentNumber::next(GoodsReceipt::class, 'receipt_number', $prefix),
                    'received_by' => $userId,
                    'received_at' => now(),
                    'status' => GoodsReceipt::STATUS_DRAFT,
                ]));
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= self::NUMBER_ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }

    private function writeLines(GoodsReceipt $receipt, array $rows): void
    {
        foreach ($rows as $row) {
            $receipt->items()->create([
                'purchase_order_item_id' => $row['purchase_order_item_id'],
                'product_id' => $row['product_id'], // taken from the order line, never from the form
                'quantity_received' => (string) $row['quantity'],
            ]);
        }
    }

    // The receipt as plain data, for the audit log. Numbers stay exact strings.
    private function snapshot(GoodsReceipt $receipt): array
    {
        return [
            'purchase_order_id' => (int) $receipt->purchase_order_id,
            'lines' => $receipt->items()->get()->map(fn($item) => [
                'purchase_order_item_id' => (int) $item->purchase_order_item_id,
                'product_id' => (int) $item->product_id,
                'quantity' => (string) $item->quantity_received,
            ])->all(),
        ];
    }

    // Checks the shape of every line and turns the quantities into exact decimals.
    private function normalizeLines(array $lines): array
    {
        $lines = array_values($lines);

        if ($lines === []) {
            throw new InvalidArgumentException('A receipt needs at least one line.');
        }

        $rows = [];
        $seen = [];

        foreach ($lines as $index => $line) {
            $number = $index + 1;
            $itemId = (int) ($line['purchase_order_item_id'] ?? 0);

            if (isset($seen[$itemId])) {
                throw new InvalidArgumentException("Line {$number}: this order line is already on the receipt.");
            }

            $seen[$itemId] = true;

            $quantity = $this->decimal($line['quantity'] ?? '', "Line {$number}: the quantity");

            // The limit is the largest value that fits DECIMAL(12,3).
            if (! $quantity->isPositive() || $quantity->isGreaterThan('999999999.999')) {
                throw new InvalidArgumentException("Line {$number}: the quantity must be greater than zero.");
            }

            $rows[] = ['purchase_order_item_id' => $itemId, 'quantity' => $quantity];
        }

        return $rows;
    }

    // Text to exact decimal. More decimals than allowed (or text that is not a number) is refused.
    private function decimal(string|int $value, string $label): BigDecimal
    {
        try {
            return BigDecimal::of((string) $value)->toScale(3);
        } catch (MathException) {
            throw new InvalidArgumentException("{$label} must be a number with at most 3 decimals.");
        }
    }
}
