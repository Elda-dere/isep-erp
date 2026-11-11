<?php

namespace Tests\Feature\Sales;

use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SaleService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SaleListScreensTest extends TestCase
{
    use RefreshDatabase;

    private SaleService $service;
    private User $cashier;
    private User $manager;
    private Customer $customer;
    private Warehouse $main;
    private Product $water;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); // no compiled frontend files needed in tests

        $this->service = app(SaleService::class);
        $this->cashier = $this->userWith('sales.create', 'sales.view');
        $this->manager = $this->userWith('sales.view', 'sales.cancel');
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
        app(StockService::class)->receive($this->water->id, $this->main->id, '100', $this->cashier->id);
    }

    // Builds a user whose role has exactly the permissions named.
    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Test role ' . uniqid()]);

        foreach ($permissions as $name) {
            $role->permissions()->attach(Permission::firstOrCreate(['name' => $name]));
        }

        $user->roles()->attach($role);

        return $user;
    }

    // A pending sale of water (10 x 2.50 = 25.00 by default).
    private function sell(?Customer $customer = null, ?User $by = null, string $quantity = '10'): Sale
    {
        return $this->service->create(($customer ?? $this->customer)->id, [
            ['product_id' => $this->water->id, 'warehouse_id' => $this->main->id, 'quantity' => $quantity],
        ], ($by ?? $this->cashier)->id);
    }

    private function stockOf(): string
    {
        return (string) InventoryStock::where('product_id', $this->water->id)
            ->where('warehouse_id', $this->main->id)
            ->value('quantity');
    }

    private function statusChanges(string $action)
    {
        return DB::table('audit_logs')->where('action', $action)->get();
    }

    public function test_users_without_sales_view_cannot_open_the_list(): void
    {
        $creator = $this->userWith('sales.create');           // may make sales, but not browse them
        $other = $this->userWith('customers.manage');

        $this->actingAs($creator)->get('/sales')->assertForbidden();
        $this->actingAs($other)->get('/sales')->assertForbidden();
        $this->actingAs($this->manager)->get('/sales')->assertOk();
    }

    public function test_the_list_shows_sales_newest_first_with_exact_totals(): void
    {
        $this->sell();                                                       // 25.00
        $zeta = Customer::create(['name' => 'Zeta Shop']);
        $second = $this->sell($zeta, null, '3');                             // 7.50

        $this->actingAs($this->manager)->get('/sales')
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('Sales/Index')
                ->has('sales.data', 2)
                ->where('sales.data.0.sale_number', $second->sale_number)    // newest first
                ->where('sales.data.0.customer', 'Zeta Shop')
                ->where('sales.data.0.status', 'pending')
                ->where('sales.data.0.created_by', $this->cashier->name)
                ->where('sales.data.0.lines_count', 1)
                ->where('sales.data.0.total_amount', '7.50')                 // exact strings
                ->where('sales.data.0.currency', 'ETB')
                ->where('sales.data.1.total_amount', '25.00')
                ->has('customers', 2)
                ->has('cashiers', 1)
                ->has('statuses', 3));
    }

    public function test_the_list_can_be_filtered_by_status_customer_and_cashier(): void
    {
        $zeta = Customer::create(['name' => 'Zeta Shop']);
        $cashier2 = $this->userWith('sales.create', 'sales.view');

        $pending = $this->sell();
        $cancelled = $this->sell($zeta);
        $this->service->cancel($cancelled, $this->manager->id);
        $byOther = $this->sell(null, $cashier2);

        $this->actingAs($this->manager)->get('/sales?status=cancelled')
            ->assertInertia(fn(Assert $page) => $page
                ->has('sales.data', 1)
                ->where('sales.data.0.sale_number', $cancelled->sale_number));

        $this->actingAs($this->manager)->get("/sales?customer_id={$zeta->id}")
            ->assertInertia(fn(Assert $page) => $page->has('sales.data', 1));

        $this->actingAs($this->manager)->get("/sales?created_by={$cashier2->id}")
            ->assertInertia(fn(Assert $page) => $page
                ->has('sales.data', 1)
                ->where('sales.data.0.sale_number', $byOther->sale_number)
                ->has('cashiers', 2));

        // Filters together: pending sales of Abebe Kebede are the first and the third.
        $this->actingAs($this->manager)->get("/sales?status=pending&customer_id={$this->customer->id}")
            ->assertInertia(fn(Assert $page) => $page->has('sales.data', 2));

        $this->assertNotNull($pending);
    }

    public function test_the_date_filter_uses_addis_ababa_business_days(): void
    {
        $sale = $this->sell();

        // 22:00 UTC on 5 January is 01:00 on 6 January in Addis Ababa (UTC+3).
        Sale::whereKey($sale->id)->update(['created_at' => '2026-01-05 22:00:00']);

        $this->actingAs($this->manager)->get('/sales?from=2026-01-06&to=2026-01-06')
            ->assertInertia(fn(Assert $page) => $page->has('sales.data', 1));

        $this->actingAs($this->manager)->get('/sales?from=2026-01-05&to=2026-01-05')
            ->assertInertia(fn(Assert $page) => $page->has('sales.data', 0));
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->actingAs($this->manager)->get('/sales?status=banana')->assertSessionHasErrors('status');
        $this->actingAs($this->manager)->get('/sales?customer_id=99999')->assertSessionHasErrors('customer_id');
        $this->actingAs($this->manager)->get('/sales?created_by=99999')->assertSessionHasErrors('created_by');
        $this->actingAs($this->manager)->get('/sales?from=2026-01-10&to=2026-01-05')->assertSessionHasErrors('to');
    }

    public function test_a_manager_can_cancel_a_pending_sale_and_the_stock_comes_back(): void
    {
        $sale = $this->sell();
        $this->assertSame('90.000', $this->stockOf());

        $this->actingAs($this->manager)->post("/sales/{$sale->id}/cancel")
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success')
            ->assertRedirect(route('sales.show', $sale));

        $this->assertSame('cancelled', $sale->fresh()->status);
        $this->assertSame('100.000', $this->stockOf());

        $reversal = StockMovement::where('movement_type', 'sale_reversal')->first();
        $this->assertSame($this->manager->id, (int) $reversal->performed_by);   // whoever cancelled

        $rows = $this->statusChanges('sale.cancelled');
        $this->assertCount(1, $rows);
        $this->assertSame($this->manager->id, (int) $rows->first()->user_id);
    }

    public function test_cancelling_needs_the_sales_cancel_permission(): void
    {
        $sale = $this->sell();
        $viewer = $this->userWith('sales.view');

        // Being able to make and look at sales is not enough to cancel one.
        $this->actingAs($viewer)->post("/sales/{$sale->id}/cancel")->assertForbidden();
        $this->actingAs($this->cashier)->post("/sales/{$sale->id}/cancel")->assertForbidden();

        $this->assertSame('pending', $sale->fresh()->status);
        $this->assertSame('90.000', $this->stockOf());
        $this->assertCount(0, $this->statusChanges('sale.cancelled'));
    }

    public function test_a_paid_or_already_cancelled_sale_cannot_be_cancelled_from_the_screen(): void
    {
        $paid = $this->sell();
        Sale::whereKey($paid->id)->update(['status' => Sale::STATUS_PAID]);
        $gone = $this->sell();
        $this->assertSame('80.000', $this->stockOf());

        // SAL-11: a paid sale cannot be cancelled, and it keeps its stock.
        $this->actingAs($this->manager)->post("/sales/{$paid->id}/cancel")->assertSessionHasErrors('status');
        $this->assertSame('paid', $paid->fresh()->status);
        $this->assertSame('80.000', $this->stockOf());

        // A sale cancelled once cannot be cancelled again (that would invent stock).
        $this->actingAs($this->manager)->post("/sales/{$gone->id}/cancel")->assertSessionHasNoErrors();
        $this->assertSame('90.000', $this->stockOf());

        $this->actingAs($this->manager)->post("/sales/{$gone->id}/cancel")->assertSessionHasErrors('status');
        $this->assertSame('90.000', $this->stockOf());                           // not 100
        $this->assertCount(1, $this->statusChanges('sale.cancelled'));
    }
}
