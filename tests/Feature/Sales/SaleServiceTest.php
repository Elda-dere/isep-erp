<?php

namespace Tests\Feature\Sales;

use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SaleService;
use App\Services\StockService;
use Brick\Math\BigDecimal;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class SaleServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stock;
    private SaleService $service;
    private User $user;
    private Customer $customer;
    private Warehouse $main;
    private Warehouse $branch;
    private Product $water;
    private Product $juice;

    protected function setUp(): void
    {
        parent::setUp();

        // The container builds the services with their dependencies, like the app does.
        $this->stock = app(StockService::class);
        $this->service = app(SaleService::class);

        $this->user = User::factory()->create();
        $this->customer = Customer::create(['name' => 'Abebe Kebede']);
        $this->main = Warehouse::create(['name' => 'Main store']);
        $this->branch = Warehouse::create(['name' => 'Branch store']);

        // Water is created first, so it has the lower id: stock goes down for it first.
        $this->water = $this->makeProduct('SKU-001', '2.50');
        $this->juice = $this->makeProduct('SKU-002', '0.50');

        // 100 of each in the main store.
        $this->stock->receive($this->water->id, $this->main->id, '100', $this->user->id);
        $this->stock->receive($this->juice->id, $this->main->id, '100', $this->user->id);
    }

    private function makeProduct(string $sku, string $price, bool $active = true): Product
    {
        return Product::create([
            'category_id' => Category::firstOrCreate(['name' => 'Beverages'])->id,
            'unit_id' => Unit::firstOrCreate(['name' => 'Piece', 'symbol' => 'pcs'])->id,
            'sku' => $sku,
            'name' => "Product {$sku}",
            'cost_price' => '1.00',
            'selling_price' => $price,
            'reorder_level' => '5.000',
            'is_active' => $active,
        ]);
    }

    // Two lines: 10 water x 2.50 = 25.00, and 0.010 juice x 0.50 = 0.005 (which rounds half-up to 0.01).
    private function lines(): array
    {
        return [
            ['product_id' => $this->water->id, 'warehouse_id' => $this->main->id, 'quantity' => '10'],
            ['product_id' => $this->juice->id, 'warehouse_id' => $this->main->id, 'quantity' => '0.010'],
        ];
    }

    private function sell(?array $lines = null, bool $override = false): Sale
    {
        return $this->service->create($this->customer->id, $lines ?? $this->lines(), $this->user->id, $override);
    }

    // One line for water in the main store.
    private function waterLine(string $quantity, ?string $price = null): array
    {
        $line = ['product_id' => $this->water->id, 'warehouse_id' => $this->main->id, 'quantity' => $quantity];

        return $price === null ? $line : $line + ['unit_price' => $price];
    }

    // The quantity of a product in a warehouse (the main store by default), as an exact string.
    private function stockOf(Product $product, ?Warehouse $warehouse = null): string
    {
        $quantity = InventoryStock::where('product_id', $product->id)
            ->where('warehouse_id', ($warehouse ?? $this->main)->id)
            ->value('quantity');

        return (string) ($quantity ?? '0.000');
    }

    private function saleMovements(string $type = 'sale')
    {
        return StockMovement::where('movement_type', $type)->orderBy('product_id')->orderBy('id')->get();
    }

    private function audit(string $action)
    {
        return DB::table('audit_logs')->where('action', $action)->orderBy('id')->get();
    }

    // The JSON columns come back as text: turn them into arrays.
    private function values(object $row, string $column): ?array
    {
        return $row->{$column} === null ? null : json_decode($row->{$column}, true);
    }

    public function test_a_sale_is_created_with_a_number_lines_totals_and_stock_taken_out(): void
    {
        Carbon::setTestNow('2026-03-01 10:00:00');

        try {
            $sale = $this->sell();

            $this->assertSame('SAL-20260301-0001', $sale->sale_number);
            $this->assertSame('pending', $sale->status);
            $this->assertSame($this->customer->id, (int) $sale->customer_id);
            $this->assertSame($this->user->id, (int) $sale->created_by);
            $this->assertSame('ETB', $sale->currency);
            $this->assertSame('25.01', $sale->fresh()->total_amount);        // exact decimal string

            $this->assertCount(2, $sale->items);
            $this->assertSame('10.000', $sale->items[0]->fresh()->quantity);
            $this->assertSame('2.50', $sale->items[0]->fresh()->unit_price);  // defaults to the selling price
            $this->assertSame('25.00', $sale->items[0]->fresh()->line_total);
            $this->assertSame('0.01', $sale->items[1]->fresh()->line_total);  // 0.005 rounds half-up

            // SAL-07: the stock went down NOW, with one "sale" movement per line.
            $this->assertSame('90.000', $this->stockOf($this->water));
            $this->assertSame('99.990', $this->stockOf($this->juice));

            $movements = $this->saleMovements();
            $this->assertCount(2, $movements);
            $this->assertSame($sale->items[0]->id, (int) $movements[0]->sale_item_id);
            $this->assertSame('-10.000', $movements[0]->fresh()->quantity);    // signed: stock out is negative
            $this->assertSame($this->user->id, (int) $movements[0]->performed_by);
        } finally {
            Carbon::setTestNow(); // always put the clock back, even if an assertion fails
        }
    }

    public function test_sale_numbers_count_up_and_follow_the_addis_ababa_day(): void
    {
        Carbon::setTestNow('2026-03-01 10:00:00');

        try {
            $this->assertSame('SAL-20260301-0001', $this->sell()->sale_number);
            $this->assertSame('SAL-20260301-0002', $this->sell()->sale_number);

            // 21:30 UTC is already 00:30 on the next day in Addis Ababa (UTC+3): a new business day.
            Carbon::setTestNow('2026-03-01 21:30:00');
            $this->assertSame('SAL-20260302-0001', $this->sell()->sale_number);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_the_total_is_the_sum_of_the_rounded_line_totals(): void
    {
        // Juice costs 0.50: 0.010 x 0.50 = 0.0050 -> 0.01, 0.009 x 0.50 = 0.0045 -> 0.00, 0.011 x 0.50 = 0.0055 -> 0.01.
        $sale = $this->sell(array_map(
            fn(string $quantity) => ['product_id' => $this->juice->id, 'warehouse_id' => $this->main->id, 'quantity' => $quantity],
            ['0.010', '0.009', '0.011'],
        ));

        $this->assertSame('0.01', $sale->items[0]->line_total);
        $this->assertSame('0.00', $sale->items[1]->line_total);
        $this->assertSame('0.01', $sale->items[2]->line_total);

        // DR-05: the total is the sum of the ROUNDED lines (0.02). Rounding the unrounded sum
        // (0.0295) would give 0.03, which is NOT what the rule says.
        $this->assertSame('0.02', $sale->fresh()->total_amount);
        $this->assertSame('0.02', $sale->linesTotal());
        $this->assertSame($sale->fresh()->total_amount, $sale->linesTotal());
    }

    public function test_changing_the_unit_price_needs_the_permission_and_is_audit_logged(): void
    {
        // Without the permission, a price other than the catalogue price (2.50) is refused.
        try {
            $this->sell([$this->waterLine('2', '2.00')]);
            $this->fail('A price change without the permission should be refused.');
        } catch (DomainException) {
            $this->assertSame(0, Sale::count());
            $this->assertSame('100.000', $this->stockOf($this->water));
        }

        // The catalogue price itself is fine without the permission, even written with other decimals.
        $same = $this->sell([$this->waterLine('2', '2.5')]);
        $this->assertSame('2.50', $same->items[0]->fresh()->unit_price);
        $this->assertCount(0, $this->audit('sale.price_overridden'));

        // With the permission, the price is accepted, and the change is audit-logged (SAL-04).
        $sale = $this->sell([$this->waterLine('2', '2.00')], true);

        $this->assertSame('2.00', $sale->items[0]->fresh()->unit_price);
        $this->assertSame('4.00', $sale->items[0]->fresh()->line_total);
        $this->assertSame('4.00', $sale->fresh()->total_amount);

        $rows = $this->audit('sale.price_overridden');
        $this->assertCount(1, $rows);
        $this->assertSame('sale_item', $rows->first()->entity_type);
        $this->assertSame($sale->items[0]->id, (int) $rows->first()->entity_id);
        $this->assertSame(['unit_price' => '2.50'], $this->values($rows->first(), 'old_values'));
        $this->assertSame('2.00', $this->values($rows->first(), 'new_values')['unit_price']);
    }

    public function test_bad_input_is_refused_and_nothing_is_saved(): void
    {
        $expensive = $this->makeProduct('BIG-001', '9999999999.99');

        $bad = [
            'no lines' => [],
            'zero quantity' => [$this->waterLine('0')],
            'negative quantity' => [$this->waterLine('-1')],
            'four decimals' => [$this->waterLine('1.2345')],
            'not a number' => [$this->waterLine('abc')],
            'negative price' => [$this->waterLine('1', '-1')],
            'three decimals in the price' => [$this->waterLine('1', '2.505')],
            'unknown product' => [['product_id' => 99999] + $this->waterLine('1')],
            'unknown warehouse' => [['warehouse_id' => 99999] + $this->waterLine('1')],
            // 2 x 9,999,999,999.99 does not fit DECIMAL(12,2).
            'total too large' => [['product_id' => $expensive->id, 'warehouse_id' => $this->main->id, 'quantity' => '2']],
        ];

        foreach ($bad as $name => $lines) {
            try {
                $this->sell($lines);
                $this->fail("This should be refused: {$name}");
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }

        try {
            $this->service->create(99999, $this->lines(), $this->user->id);
            $this->fail('An unknown customer should be refused.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $this->assertSame(0, Sale::count());
        $this->assertSame(0, SaleItem::count());
        $this->assertSame('100.000', $this->stockOf($this->water));
        $this->assertCount(0, $this->saleMovements());
    }

    public function test_an_inactive_product_cannot_be_sold(): void
    {
        $old = $this->makeProduct('OLD-001', '1.00', false);

        try {
            $this->sell([['product_id' => $old->id, 'warehouse_id' => $this->main->id, 'quantity' => '1']]);
            $this->fail('An inactive product should be refused (PRD-04).');
        } catch (DomainException $e) {
            $this->assertStringContainsString('OLD-001', $e->getMessage());
            $this->assertSame(0, Sale::count());
        }
    }

    public function test_a_sale_cannot_take_more_than_is_available_and_changes_nothing(): void
    {
        try {
            $this->sell([$this->waterLine('100.001')]);
            $this->fail('Selling more than the stock should be refused.');
        } catch (DomainException $e) {
            // INV-07: the message says which line, where, and how much IS available.
            $this->assertStringContainsString('Line 1', $e->getMessage());
            $this->assertStringContainsString('100.000', $e->getMessage());
            $this->assertStringContainsString('Main store', $e->getMessage());
        }

        $this->assertSame(0, Sale::count());
        $this->assertSame(0, SaleItem::count());
        $this->assertSame('100.000', $this->stockOf($this->water));
        $this->assertCount(0, $this->saleMovements());

        // Exactly what is available can be sold ...
        $this->sell([$this->waterLine('100')]);
        $this->assertSame('0.000', $this->stockOf($this->water));

        // ... and then not even a little more.
        try {
            $this->sell([$this->waterLine('0.001')]);
            $this->fail('There is nothing left to sell.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('only 0.000 is available', $e->getMessage());
        }
    }

    public function test_a_failure_on_a_later_line_undoes_the_whole_sale(): void
    {
        // The water line is fine, but the juice line is short of stock.
        $lines = [
            $this->waterLine('10'),
            ['product_id' => $this->juice->id, 'warehouse_id' => $this->main->id, 'quantity' => '100.001'],
        ];

        try {
            $this->sell($lines);
            $this->fail('The second line should fail.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Line 2', $e->getMessage());
        }

        // SAL-13: nothing is saved. The water that WAS taken out before the failure is back.
        $this->assertSame(0, Sale::count());
        $this->assertSame(0, SaleItem::count());
        $this->assertCount(0, $this->saleMovements());
        $this->assertSame('100.000', $this->stockOf($this->water));
        $this->assertSame('100.000', $this->stockOf($this->juice));
        $this->assertCount(0, $this->audit('sale.created'));
    }

    public function test_the_same_product_on_two_lines_counts_together(): void
    {
        // 60 + 60 is more than the 100 available, even though each line alone would fit.
        try {
            $this->sell([$this->waterLine('60'), $this->waterLine('60')]);
            $this->fail('The second line should fail.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Line 2', $e->getMessage());
            $this->assertStringContainsString('only 40.000 is available', $e->getMessage());
        }

        $this->assertSame(0, Sale::count());
        $this->assertSame('100.000', $this->stockOf($this->water));

        // 50 + 50 fits exactly.
        $sale = $this->sell([$this->waterLine('50'), $this->waterLine('50')]);
        $this->assertCount(2, $sale->items);
        $this->assertSame('0.000', $this->stockOf($this->water));
    }

    public function test_stock_is_checked_in_the_chosen_warehouse(): void
    {
        // The branch store has no water, even though the main store has 100.
        try {
            $this->sell([['product_id' => $this->water->id, 'warehouse_id' => $this->branch->id, 'quantity' => '1']]);
            $this->fail('The branch store has no stock.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Branch store', $e->getMessage());
            $this->assertStringContainsString('only 0.000 is available', $e->getMessage());
        }

        $this->assertSame('100.000', $this->stockOf($this->water));
        $this->assertSame(0, Sale::count());
    }

    public function test_cancelling_a_pending_sale_restores_the_stock_and_is_logged(): void
    {
        $this->actingAs($this->user); // so the audit logger records who did it
        $sale = $this->sell();
        $other = User::factory()->create();

        $cancelled = $this->service->cancel($sale, $other->id);

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame('cancelled', $sale->fresh()->status);
        $this->assertSame('100.000', $this->stockOf($this->water));
        $this->assertSame('100.000', $this->stockOf($this->juice));

        // One reversal movement per line, linked to the sale line, made by whoever cancelled.
        $reversals = $this->saleMovements('sale_reversal');
        $this->assertCount(2, $reversals);
        $this->assertSame($sale->items[0]->id, (int) $reversals[0]->sale_item_id);
        $this->assertSame('10.000', $reversals[0]->fresh()->quantity);
        $this->assertSame($other->id, (int) $reversals[0]->performed_by);

        // DR-04: the stock still equals the sum of its movements.
        $sum = StockMovement::where('product_id', $this->water->id)->where('warehouse_id', $this->main->id)->sum('quantity');
        $this->assertTrue(BigDecimal::of((string) $sum)->isEqualTo($this->stockOf($this->water)));

        $rows = $this->audit('sale.cancelled');
        $this->assertCount(1, $rows);
        $this->assertSame($this->user->id, (int) $rows->first()->user_id);
        $this->assertSame($sale->id, (int) $rows->first()->entity_id);
        $this->assertSame(['status' => 'pending'], $this->values($rows->first(), 'old_values'));
        $this->assertSame(['status' => 'cancelled'], $this->values($rows->first(), 'new_values'));
    }

    public function test_a_sale_cannot_be_cancelled_twice_or_after_it_is_paid(): void
    {
        // Twice: the stock must not be restored a second time (that would invent stock).
        $first = $this->sell();
        $this->service->cancel($first, $this->user->id);

        try {
            $this->service->cancel($first, $this->user->id);
            $this->fail('A cancelled sale cannot be cancelled again.');
        } catch (DomainException) {
            $this->assertSame('100.000', $this->stockOf($this->water)); // not 110
            $this->assertCount(2, $this->saleMovements('sale_reversal'));
        }

        // SAL-11: a PAID sale cannot be cancelled (the payments slice will set the status to paid).
        $paid = $this->sell();
        Sale::whereKey($paid->id)->update(['status' => Sale::STATUS_PAID]);

        try {
            $this->service->cancel($paid, $this->user->id);
            $this->fail('A paid sale cannot be cancelled.');
        } catch (DomainException) {
            $this->assertSame('paid', $paid->fresh()->status);
            $this->assertSame('90.000', $this->stockOf($this->water));        // the paid sale still holds its stock
            $this->assertCount(2, $this->saleMovements('sale_reversal'));     // no new reversals
        }
    }

    public function test_creating_a_sale_is_audit_logged_and_refused_actions_write_nothing(): void
    {
        $this->actingAs($this->user); // so the audit logger records who did it

        $sale = $this->sell();

        $rows = $this->audit('sale.created');
        $this->assertCount(1, $rows);
        $this->assertSame($this->user->id, (int) $rows->first()->user_id);
        $this->assertSame($sale->id, (int) $rows->first()->entity_id);

        $new = $this->values($rows->first(), 'new_values');
        $this->assertSame($sale->sale_number, $new['sale_number']);
        $this->assertSame('25.01', $new['total_amount']);
        $this->assertSame($this->customer->id, $new['customer_id']);
        $this->assertCount(2, $new['lines']);
        $this->assertSame('25.00', $new['lines'][0]['line_total']);

        // A refused sale (not enough stock) leaves no audit row behind.
        try {
            $this->sell([$this->waterLine('1000')]);
        } catch (DomainException) {
            // expected
        }

        $this->assertCount(1, $this->audit('sale.created'));
    }
}
