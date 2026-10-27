<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Support\DocumentNumber;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Owns the purchase order rules and EVERY purchase order status change.
 * Controllers (and later, jobs) call this class instead of changing orders themselves.
 *
 * Each line is an array like: ['product_id' => 3, 'quantity' => '10', 'unit_cost' => '2.50'].
 * Quantities and money are passed as strings, never floats.
 *
 * Two kinds of error: InvalidArgumentException for bad input (a quantity of 0, an unknown product),
 * DomainException when a business rule refuses (an inactive product, a wrong status).
 */
class PurchaseOrderService
{
    private const NUMBER_ATTEMPTS = 5;               // retries if two people get the same number at once
    private const BUSINESS_TIMEZONE = 'Africa/Addis_Ababa';

    // PRC-02: creates a draft order with its lines and a generated number.
    public function createDraft(int $supplierId, array $lines, int $userId): PurchaseOrder
    {
        // Check the input first, so a bad line never leaves a half-built order behind.
        $rows = $this->normalizeLines($lines);

        return DB::transaction(function () use ($supplierId, $rows, $userId) {
            $order = $this->createWithNumber($supplierId, $userId);
            $this->writeLines($order, $rows);

            AuditLogger::log(
                'purchase_order.created',
                'purchase_order',
                $order->id,
                null,
                $this->snapshot($order) + ['order_number' => $order->order_number],
            );

            return $order->load('items');
        });
    }

    // PRC-03: supplier and lines can change only while the order is a draft.
    public function updateDraft(PurchaseOrder $order, int $supplierId, array $lines, int $userId): PurchaseOrder
    {
        $rows = $this->normalizeLines($lines);

        return DB::transaction(function () use ($order, $supplierId, $rows) {
            $order = $this->lock($order);

            if ($order->status !== PurchaseOrder::STATUS_DRAFT) {
                throw new DomainException('Lines can be edited only while the order is a draft.');
            }

            $before = $this->snapshot($order);

            $order->update(['supplier_id' => $supplierId]);
            $order->items()->delete();           // a draft has no receipts, so replacing its lines is safe
            $this->writeLines($order, $rows);

            $after = $this->snapshot($order);

            if ($before !== $after) {
                AuditLogger::log('purchase_order.updated', 'purchase_order', $order->id, $before, $after);
            }

            return $order->load('items');
        });
    }

    // PRC-03: draft -> ordered. ordered_at is set now.
    public function place(PurchaseOrder $order, int $userId): PurchaseOrder
    {
        return DB::transaction(function () use ($order) {
            $order = $this->lock($order);

            if ($order->status !== PurchaseOrder::STATUS_DRAFT) {
                throw new DomainException("Only a draft order can be placed (this one is {$order->status}).");
            }

            if (! $order->items()->exists()) {
                throw new DomainException('An order without lines cannot be placed.');
            }

            return $this->changeStatus($order, PurchaseOrder::STATUS_ORDERED, ['ordered_at' => now()]);
        });
    }

    // PRC-05: a draft or ordered order can be cancelled, but only while nothing has been received.
    public function cancel(PurchaseOrder $order, int $userId): PurchaseOrder
    {
        return DB::transaction(function () use ($order) {
            $order = $this->lock($order);

            if ($order->status === PurchaseOrder::STATUS_CANCELLED) {
                throw new DomainException('This order is already cancelled.');
            }

            if (! in_array($order->status, [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_ORDERED], true)) {
                throw new DomainException('Goods have already been received against this order, so it cannot be cancelled.');
            }

            // Belt and braces: even if the status says "ordered", a posted receipt means goods arrived.
            // (A direct query for now: the goods receipt model comes with the receipts slice.)
            $received = DB::table('goods_receipts')
                ->where('purchase_order_id', $order->id)
                ->where('status', 'posted')
                ->exists();

            if ($received) {
                throw new DomainException('Goods have already been received against this order, so it cannot be cancelled.');
            }

            return $this->changeStatus($order, PurchaseOrder::STATUS_CANCELLED);
        });
    }

    // The one place where a status changes: update it, then write the audit row (AUD-02).
    private function changeStatus(PurchaseOrder $order, string $newStatus, array $extra = []): PurchaseOrder
    {
        $oldStatus = $order->status;

        $order->update(['status' => $newStatus] + $extra);

        AuditLogger::log('purchase_order.status_changed', 'purchase_order', $order->id, ['status' => $oldStatus], ['status' => $newStatus]);

        return $order;
    }

    // Re-reads the order with a lock, so two people cannot change the same order at the same moment.
    private function lock(PurchaseOrder $order): PurchaseOrder
    {
        return PurchaseOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
    }

    // Creates the order row with the next number. If two people ask for the same number at the
    // same time, the database's unique index refuses one of them and that one simply tries again.
    private function createWithNumber(int $supplierId, int $userId): PurchaseOrder
    {
        // The year is the business year in Addis Ababa, not UTC.
        $prefix = 'PO-' . now(self::BUSINESS_TIMEZONE)->year . '-';

        for ($attempt = 1;; $attempt++) {
            try {
                // A nested transaction (a savepoint): a failed insert leaves the outer transaction usable.
                return DB::transaction(fn() => PurchaseOrder::create([
                    'supplier_id' => $supplierId,
                    'order_number' => DocumentNumber::next(PurchaseOrder::class, 'order_number', $prefix),
                    'status' => PurchaseOrder::STATUS_DRAFT,
                    'created_by' => $userId,
                ]));
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= self::NUMBER_ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }

    private function writeLines(PurchaseOrder $order, array $rows): void
    {
        foreach ($rows as $row) {
            $order->items()->create([
                'product_id' => $row['product_id'],
                'quantity' => (string) $row['quantity'],
                'unit_cost' => (string) $row['unit_cost'],
            ]);
        }
    }

    // The order as plain data, for the audit log. Numbers stay exact strings.
    private function snapshot(PurchaseOrder $order): array
    {
        return [
            'supplier_id' => (int) $order->supplier_id,
            'lines' => $order->items()->get()->map(fn($item) => [
                'product_id' => (int) $item->product_id,
                'quantity' => (string) $item->quantity,
                'unit_cost' => (string) $item->unit_cost,
            ])->all(),
        ];
    }

    // Checks every line and turns the numbers into exact decimals.
    private function normalizeLines(array $lines): array
    {
        $lines = array_values($lines);

        if ($lines === []) {
            throw new InvalidArgumentException('An order needs at least one line.');
        }

        // One query for all the products, not one per line.
        $products = Product::whereIn('id', array_column($lines, 'product_id'))->get()->keyBy('id');

        $rows = [];

        foreach ($lines as $index => $line) {
            $number = $index + 1;
            $product = $products->get((int) ($line['product_id'] ?? 0));

            if (! $product) {
                throw new InvalidArgumentException("Line {$number}: the product does not exist.");
            }

            // PRD-04: inactive products cannot be added to new orders.
            if (! $product->is_active) {
                throw new DomainException("Line {$number}: product {$product->sku} is inactive and cannot be added to a purchase order.");
            }

            $quantity = $this->decimal($line['quantity'] ?? '', 3, "Line {$number}: the quantity");
            $unitCost = $this->decimal($line['unit_cost'] ?? '', 2, "Line {$number}: the unit cost");

            // The limits are the largest values that fit DECIMAL(12,3) and DECIMAL(12,2).
            if (! $quantity->isPositive() || $quantity->isGreaterThan('999999999.999')) {
                throw new InvalidArgumentException("Line {$number}: the quantity must be greater than zero.");
            }

            if ($unitCost->isNegative() || $unitCost->isGreaterThan('9999999999.99')) {
                throw new InvalidArgumentException("Line {$number}: the unit cost cannot be negative.");
            }

            $rows[] = ['product_id' => $product->id, 'quantity' => $quantity, 'unit_cost' => $unitCost];
        }

        return $rows;
    }

    // Text to exact decimal. More decimals than allowed (or text that is not a number) is refused.
    private function decimal(string|int $value, int $scale, string $label): BigDecimal
    {
        try {
            return BigDecimal::of((string) $value)->toScale($scale);
        } catch (MathException) {
            throw new InvalidArgumentException("{$label} must be a number with at most {$scale} decimals.");
        }
    }
}
