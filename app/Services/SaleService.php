<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Warehouse;
use App\Support\DocumentNumber;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Owns creating and cancelling sales.
 *
 * Stock changes go ONLY through StockService. Marking a sale as paid will belong to PaymentService
 * (the payments slice), so there is deliberately no "markPaid" here yet.
 *
 * Each line is an array like:
 *   ['product_id' => 3, 'warehouse_id' => 1, 'quantity' => '2.5', 'unit_price' => '12.00'].
 * "unit_price" is optional: without it, the product's selling price is used.
 * Quantities and money are passed as strings, never floats.
 *
 * Two kinds of error: InvalidArgumentException for bad input (a quantity of 0, an unknown product),
 * DomainException when a business rule refuses (not enough stock, an inactive product, a wrong status).
 */
class SaleService
{
    private const NUMBER_ATTEMPTS = 5;               // retries if two cashiers get the same number at once
    private const TRANSACTION_ATTEMPTS = 3;          // retries if MySQL reports a deadlock
    private const BUSINESS_TIMEZONE = 'Africa/Addis_Ababa';
    private const MAX_MONEY = '9999999999.99';       // the largest value that fits DECIMAL(12,2)
    private const MAX_QUANTITY = '999999999.999';    // the largest value that fits DECIMAL(12,3)

    public function __construct(private StockService $stock) {}

    // SAL-13: creates a pending sale. In ONE transaction: the sale, its lines, and the stock going
    // down. If anything fails (for example, not enough stock on the last line), nothing is saved.
    // $canOverridePrice comes from the caller (the sales.price_override permission): this class
    // does not check permissions itself.
    public function create(int $customerId, array $lines, int $userId, bool $canOverridePrice = false): Sale
    {
        $customer = Customer::find($customerId);

        if (! $customer) {
            throw new InvalidArgumentException('The customer does not exist.');
        }

        // Check the input first, so a bad line never leaves a half-built sale behind.
        $rows = $this->normalizeLines($lines, $canOverridePrice);

        // DR-05: the sale total is the sum of the line totals (each already rounded).
        $total = BigDecimal::zero()->toScale(2);

        foreach ($rows as $row) {
            $total = $total->plus($row['line_total']);
        }

        if ($total->isGreaterThan(self::MAX_MONEY)) {
            throw new InvalidArgumentException('The total of the sale is too large.');
        }

        return DB::transaction(function () use ($customer, $rows, $total, $userId) {
            $sale = $this->createWithNumber($customer->id, $total, $userId);
            $items = $this->writeItems($sale, $rows);

            // SAL-07: stock is held while the customer pays, so it goes down NOW.
            $this->deductStock($rows, $items, $userId);

            AuditLogger::log('sale.created', 'sale', $sale->id, null, [
                'sale_number' => $sale->sale_number,
                'customer_id' => (int) $customer->id,
                'currency' => $sale->currency,
                'total_amount' => (string) $total,
                'lines' => collect($rows)->map(fn($row) => [
                    'product_id' => (int) $row['product']->id,
                    'warehouse_id' => (int) $row['warehouse']->id,
                    'quantity' => (string) $row['quantity'],
                    'unit_price' => (string) $row['unit_price'],
                    'line_total' => (string) $row['line_total'],
                ])->all(),
            ]);

            // SAL-04: a price changed from the catalogue price is audit-logged.
            foreach ($rows as $index => $row) {
                if ($row['overridden']) {
                    AuditLogger::log(
                        'sale.price_overridden',
                        'sale_item',
                        $items[$index]->id,
                        ['unit_price' => (string) $row['catalogue_price']],
                        [
                            'unit_price' => (string) $row['unit_price'],
                            'sale_id' => (int) $sale->id,
                            'product_id' => (int) $row['product']->id,
                        ],
                    );
                }
            }

            return $sale->load('items');
        }, self::TRANSACTION_ATTEMPTS);
    }

    // SAL-07 / SAL-11: only a PENDING sale can be cancelled. The stock it held is restored with
    // sale_reversal movements, in the same transaction as the status change.
    public function cancel(Sale $sale, int $userId): Sale
    {
        return DB::transaction(function () use ($sale, $userId) {
            // Re-read the sale under a lock: the copy we were given may be out of date.
            $sale = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($sale->status === Sale::STATUS_PAID) {
                throw new DomainException('A paid sale cannot be cancelled.');
            }

            if ($sale->status === Sale::STATUS_CANCELLED) {
                throw new DomainException('This sale is already cancelled.');
            }

            // Product order, the same order used when the stock was taken out, so two cancellations
            // never wait for each other's stock rows in opposite orders (no deadlock).
            $items = $sale->items()->reorder()->orderBy('product_id')->orderBy('warehouse_id')->orderBy('id')->get();

            foreach ($items as $item) {
                // Passing the sale line's id lets StockService refuse to reverse the same line twice.
                $this->stock->reverse(
                    (int) $item->product_id,
                    (int) $item->warehouse_id,
                    (string) $item->quantity,
                    $userId,
                    (int) $item->id,
                );
            }

            $sale->update(['status' => Sale::STATUS_CANCELLED]);

            AuditLogger::log(
                'sale.cancelled',
                'sale',
                $sale->id,
                ['status' => Sale::STATUS_PENDING],
                ['status' => Sale::STATUS_CANCELLED],
            );

            return $sale;
        }, self::TRANSACTION_ATTEMPTS);
    }

    // Creates the sale row with the next number for TODAY (Addis Ababa day): SAL-20261108-0001.
    // If two cashiers ask for the same number at the same time, the database's unique index refuses
    // one of them and that one simply tries again.
    private function createWithNumber(int $customerId, BigDecimal $total, int $userId): Sale
    {
        $prefix = 'SAL-' . now(self::BUSINESS_TIMEZONE)->format('Ymd') . '-';

        for ($attempt = 1;; $attempt++) {
            try {
                // A nested transaction (a savepoint): a failed insert leaves the outer transaction usable.
                return DB::transaction(fn() => Sale::create([
                    'sale_number' => DocumentNumber::next(Sale::class, 'sale_number', $prefix),
                    'customer_id' => $customerId,
                    'status' => Sale::STATUS_PENDING,
                    'total_amount' => (string) $total,
                    'currency' => Sale::DEFAULT_CURRENCY,
                    'created_by' => $userId,
                ]));
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= self::NUMBER_ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }

    // Saves the lines in the order the cashier entered them. Returns the saved lines, keyed like $rows.
    private function writeItems(Sale $sale, array $rows): array
    {
        $items = [];

        foreach ($rows as $index => $row) {
            $items[$index] = $sale->items()->create([
                'product_id' => $row['product']->id,
                'warehouse_id' => $row['warehouse']->id,
                'quantity' => (string) $row['quantity'],
                'unit_price' => (string) $row['unit_price'],
                'line_total' => (string) $row['line_total'],
            ]);
        }

        return $items;
    }

    // Takes the stock out, ONLY through StockService, one call per line.
    // Lines are sent in product order (not entry order), so two sales with the same products in a
    // different order never wait for each other's stock rows in opposite orders (no deadlock).
    private function deductStock(array $rows, array $items, int $userId): void
    {
        $order = array_keys($rows);

        usort($order, fn($a, $b) => [$rows[$a]['product']->id, $rows[$a]['warehouse']->id, $a]
            <=> [$rows[$b]['product']->id, $rows[$b]['warehouse']->id, $b]);

        foreach ($order as $index) {
            $row = $rows[$index];

            try {
                // Passing the sale line's id lets StockService refuse to take stock for the same line twice.
                $this->stock->deduct(
                    (int) $row['product']->id,
                    (int) $row['warehouse']->id,
                    (string) $row['quantity'],
                    $userId,
                    (int) $items[$index]->id,
                );
            } catch (InsufficientStockException $e) {
                // INV-07: say which line is short and how much IS available. This rolls back the whole sale.
                throw new DomainException(
                    "Line {$row['number']}: product {$row['product']->sku}: only {$e->available} is available in {$row['warehouse']->name}, {$e->requested} requested."
                );
            }
        }
    }

    // Checks every line and turns the numbers into exact decimals.
    private function normalizeLines(array $lines, bool $canOverridePrice): array
    {
        $lines = array_values($lines);

        if ($lines === []) {
            throw new InvalidArgumentException('A sale needs at least one line.');
        }

        // One query for all the products and one for all the warehouses, not one per line.
        $products = Product::whereIn('id', array_column($lines, 'product_id'))->get()->keyBy('id');
        $warehouses = Warehouse::whereIn('id', array_column($lines, 'warehouse_id'))->get()->keyBy('id');

        $rows = [];

        foreach ($lines as $index => $line) {
            $number = $index + 1;
            $product = $products->get((int) ($line['product_id'] ?? 0));
            $warehouse = $warehouses->get((int) ($line['warehouse_id'] ?? 0));

            if (! $product) {
                throw new InvalidArgumentException("Line {$number}: the product does not exist.");
            }

            // PRD-04: inactive products cannot be added to new sales.
            if (! $product->is_active) {
                throw new DomainException("Line {$number}: product {$product->sku} is inactive and cannot be added to a sale.");
            }

            if (! $warehouse) {
                throw new InvalidArgumentException("Line {$number}: the warehouse does not exist.");
            }

            $quantity = $this->decimal($line['quantity'] ?? '', 3, "Line {$number}: the quantity");

            if (! $quantity->isPositive() || $quantity->isGreaterThan(self::MAX_QUANTITY)) {
                throw new InvalidArgumentException("Line {$number}: the quantity must be greater than zero.");
            }

            // SAL-03: the unit price defaults to the product's selling price.
            $catalogue = BigDecimal::of((string) $product->selling_price)->toScale(2);
            $raw = $line['unit_price'] ?? null;
            $overridden = false;

            if ($raw === null || $raw === '') {
                $unitPrice = $catalogue;
            } else {
                $unitPrice = $this->decimal($raw, 2, "Line {$number}: the unit price");

                if ($unitPrice->isNegative() || $unitPrice->isGreaterThan(self::MAX_MONEY)) {
                    throw new InvalidArgumentException("Line {$number}: the unit price cannot be negative.");
                }

                // SAL-04: a price different from the catalogue price needs the permission.
                if (! $unitPrice->isEqualTo($catalogue)) {
                    if (! $canOverridePrice) {
                        throw new DomainException("Line {$number}: changing the unit price needs the price override permission.");
                    }

                    $overridden = true;
                }
            }

            // SAL-03 / DR-03: quantity x unit price, rounded half-up to 2 decimals.
            // Example: 0.010 x 0.50 = 0.005, which rounds UP to 0.01.
            $lineTotal = $quantity->multipliedBy($unitPrice)->toScale(2, RoundingMode::HalfUp);

            $rows[] = [
                'number' => $number,
                'product' => $product,
                'warehouse' => $warehouse,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'catalogue_price' => $catalogue,
                'overridden' => $overridden,
                'line_total' => $lineTotal,
            ];
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
