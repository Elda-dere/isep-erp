<?php

namespace Tests\Feature\Sales;

use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SaleService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SaleExpiryTest extends TestCase
{
    use RefreshDatabase;

    private SaleService $service;
    private User $user;
    private Customer $customer;
    private Warehouse $main;
    private Product $water;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SaleService::class);
        $this->user = User::factory()->create();
        $this->customer = Customer::create(['name' => 'Abebe Kebede']);
        $this->main = Warehouse::create(['name' => 'Main store']);
        $this->water = Product::create([
            'category_id' => Category::create(['name' => 'Beverages'])->id,
            'unit_id' => Unit::create(['name' => 'Piece', 'symbol' => 'pcs'])->id,
            'sku' => 'SKU-001',
            'name' => 'Water 1L',
            'cost_price' => '1.00',
            'selling_price' => '2.50',
            'reorder_level' => '5.000',
            'is_active' => true,
        ]);

        // 100 in the main store.
        app(StockService::class)->receive($this->water->id, $this->main->id, '100', $this->user->id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // never leave a frozen clock behind, even when a test fails
        parent::tearDown();
    }

    // A pending sale of water, created "now" (the clock may be frozen by the test).
    private function sell(string $quantity = '10'): Sale
    {
        return $this->service->create($this->customer->id, [
            ['product_id' => $this->water->id, 'warehouse_id' => $this->main->id, 'quantity' => $quantity],
        ], $this->user->id);
    }

    private function stockOf(): string
    {
        return (string) InventoryStock::where('product_id', $this->water->id)
            ->where('warehouse_id', $this->main->id)
            ->value('quantity');
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

    public function test_a_pending_sale_expires_after_30_minutes_and_its_stock_comes_back(): void
    {
        Carbon::setTestNow('2026-03-01 10:00:00');
        $sale = $this->sell('10');
        $this->assertSame('90.000', $this->stockOf());

        // 29 minutes 59 seconds: still within the limit.
        Carbon::setTestNow('2026-03-01 10:29:59');
        $this->assertSame(0, $this->service->expireStale());
        $this->assertSame('pending', $sale->fresh()->status);
        $this->assertSame('90.000', $this->stockOf());

        // 30 minutes: unpaid for 30 minutes, so it expires (SAL-08).
        Carbon::setTestNow('2026-03-01 10:30:00');
        $this->assertSame(1, $this->service->expireStale());

        $this->assertSame('cancelled', $sale->fresh()->status);
        $this->assertSame('100.000', $this->stockOf());

        // The stock card says what happened: a reversal of the sale line, with an "automatic" note.
        $reversal = StockMovement::where('movement_type', 'sale_reversal')->first();
        $this->assertSame($sale->items[0]->id, (int) $reversal->sale_item_id);
        $this->assertSame('10.000', $reversal->quantity);
        $this->assertSame($this->user->id, (int) $reversal->performed_by);   // the sale's creator: there is no acting user
        $this->assertStringContainsString('Automatic', (string) $reversal->note);

        // DR-04: the stock still equals the sum of its movements.
        $sum = StockMovement::where('product_id', $this->water->id)->sum('quantity');
        $this->assertSame('100.000', number_format((float) $sum, 3, '.', ''));

        // AUD-03: a system action has NO user in the audit log.
        $rows = $this->audit('sale.expired');
        $this->assertCount(1, $rows);
        $this->assertNull($rows->first()->user_id);
        $this->assertSame($sale->id, (int) $rows->first()->entity_id);
        $this->assertSame(['status' => 'pending'], $this->values($rows->first(), 'old_values'));
        $this->assertSame('cancelled', $this->values($rows->first(), 'new_values')['status']);
    }

    public function test_paid_and_cancelled_sales_are_never_touched(): void
    {
        Carbon::setTestNow('2026-03-01 10:00:00');

        $paid = $this->sell('10');
        Sale::whereKey($paid->id)->update(['status' => Sale::STATUS_PAID]);

        $cancelled = $this->sell('5');
        $this->service->cancel($cancelled, $this->user->id);

        // An hour later: both are old, but neither is pending.
        Carbon::setTestNow('2026-03-01 11:00:00');
        $this->assertSame(0, $this->service->expireStale());

        $this->assertSame('paid', $paid->fresh()->status);
        $this->assertSame('cancelled', $cancelled->fresh()->status);
        $this->assertSame('90.000', $this->stockOf());                        // the paid sale still holds its 10
        $this->assertCount(1, StockMovement::where('movement_type', 'sale_reversal')->get()); // only the manual cancellation
        $this->assertCount(0, $this->audit('sale.expired'));
    }

    public function test_only_old_pending_sales_expire_each_on_time(): void
    {
        Carbon::setTestNow('2026-03-01 10:00:00');
        $first = $this->sell('10');

        Carbon::setTestNow('2026-03-01 10:20:00');
        $second = $this->sell('5');
        $this->assertSame('85.000', $this->stockOf());

        // 35 minutes after the first sale, 15 after the second: only the first expires.
        Carbon::setTestNow('2026-03-01 10:35:00');
        $this->assertSame(1, $this->service->expireStale());
        $this->assertSame('cancelled', $first->fresh()->status);
        $this->assertSame('pending', $second->fresh()->status);
        $this->assertSame('95.000', $this->stockOf());

        Carbon::setTestNow('2026-03-01 10:51:00');
        $this->assertSame(1, $this->service->expireStale());
        $this->assertSame('cancelled', $second->fresh()->status);
        $this->assertSame('100.000', $this->stockOf());

        Carbon::setTestNow('2026-03-01 10:52:00');
        $this->assertSame(0, $this->service->expireStale());
    }

    public function test_the_time_limit_is_configurable(): void
    {
        config(['sales.pending_expiry_minutes' => 5]);

        Carbon::setTestNow('2026-03-01 10:00:00');
        $sale = $this->sell('10');

        Carbon::setTestNow('2026-03-01 10:04:59');
        $this->assertSame(0, $this->service->expireStale());

        Carbon::setTestNow('2026-03-01 10:05:00');
        $this->assertSame(1, $this->service->expireStale());
        $this->assertSame('cancelled', $sale->fresh()->status);
    }

    public function test_running_the_job_again_never_restores_stock_twice(): void
    {
        Carbon::setTestNow('2026-03-01 10:00:00');
        $this->sell('10');

        Carbon::setTestNow('2026-03-01 10:31:00');
        $this->assertSame(1, $this->service->expireStale());
        $this->assertSame(0, $this->service->expireStale());
        $this->assertSame(0, $this->service->expireStale());

        $this->assertSame('100.000', $this->stockOf());                       // not 110 or 120
        $this->assertCount(1, StockMovement::where('movement_type', 'sale_reversal')->get());
        $this->assertCount(1, $this->audit('sale.expired'));
    }

    public function test_the_command_expires_sales_and_reports_how_many(): void
    {
        Carbon::setTestNow('2026-03-01 10:00:00');
        $sale = $this->sell('10');

        Carbon::setTestNow('2026-03-01 10:31:00');

        $this->artisan('sales:expire')
            ->expectsOutputToContain('Expired 1 unpaid sale(s).')
            ->assertSuccessful();

        $this->assertSame('cancelled', $sale->fresh()->status);
        $this->assertSame('100.000', $this->stockOf());
    }

    public function test_the_command_is_scheduled_every_five_minutes(): void
    {
        // Booting the console loads routes/console.php, where the schedule is defined.
        Artisan::call('schedule:list');

        // Ask the scheduler itself instead of reading the text of "schedule:list",
        // whose columns are padded with extra spaces.
        $events = collect(app(Schedule::class)->events())
            ->filter(fn($event) => str_contains((string) $event->command, 'sales:expire'));

        $this->assertCount(1, $events, 'sales:expire must be scheduled exactly once (routes/console.php).');
        $this->assertSame('*/5 * * * *', $events->first()->expression);
    }
}
