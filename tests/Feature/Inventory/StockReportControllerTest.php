<?php

namespace Tests\Feature\Inventory;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StockReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stock;
    private Warehouse $main;
    private Warehouse $branch;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); // no compiled frontend files needed in tests

        $this->stock = new StockService();
        $this->main = Warehouse::create(['name' => 'Main store']);
        $this->branch = Warehouse::create(['name' => 'Branch store']);
        $this->user = $this->userWith('inventory.view');
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

    private function makeProduct(string $sku, string $name, string $reorder = '5.000', ?Category $category = null, bool $active = true): Product
    {
        return Product::create([
            'category_id' => ($category ?? Category::firstOrCreate(['name' => 'Beverages']))->id,
            'unit_id' => Unit::firstOrCreate(['name' => 'Piece', 'symbol' => 'pcs'])->id,
            'sku' => $sku,
            'name' => $name,
            'cost_price' => '10.00',
            'selling_price' => '15.00',
            'reorder_level' => $reorder,
            'is_active' => $active,
        ]);
    }

    public function test_users_without_inventory_view_get_403(): void
    {
        $product = $this->makeProduct('SKU-1', 'Water');
        $adjustOnly = $this->userWith('inventory.adjust');

        foreach (['/stock', '/stock/low', "/stock/card/{$product->id}"] as $url) {
            $this->actingAs($adjustOnly)->get($url)->assertForbidden();
        }
    }

    public function test_overview_shows_total_and_per_warehouse_quantities(): void
    {
        $water = $this->makeProduct('SKU-1', 'Water');
        $this->stock->receive($water->id, $this->main->id, '10', $this->user->id);
        $this->stock->receive($water->id, $this->branch->id, '2.5', $this->user->id);

        $this->actingAs($this->user)->get('/stock')
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('Inventory/Stock/Overview')
                ->has('products.data', 1)
                ->where('products.data.0.total_quantity', '12.500')
                ->has('products.data.0.stocks', 2));
    }

    public function test_overview_shows_zero_for_products_without_stock(): void
    {
        $this->makeProduct('SKU-1', 'Never stocked');

        $this->actingAs($this->user)->get('/stock')
            ->assertInertia(fn(Assert $page) => $page
                ->where('products.data.0.total_quantity', '0.000')
                ->has('products.data.0.stocks', 0));
    }

    public function test_overview_can_be_filtered_by_warehouse(): void
    {
        $water = $this->makeProduct('SKU-1', 'Water');
        $juice = $this->makeProduct('SKU-2', 'Juice');
        $this->stock->receive($water->id, $this->main->id, '10', $this->user->id);
        $this->stock->receive($water->id, $this->branch->id, '4', $this->user->id);
        $this->stock->receive($juice->id, $this->branch->id, '7', $this->user->id);

        // Only products stocked in Main store, and the total counts Main store only.
        $this->actingAs($this->user)->get("/stock?warehouse_id={$this->main->id}")
            ->assertInertia(fn(Assert $page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.sku', 'SKU-1')
                ->where('products.data.0.total_quantity', '10.000')
                ->has('products.data.0.stocks', 1));
    }

    public function test_overview_can_be_filtered_by_category_and_text(): void
    {
        $drinks = Category::create(['name' => 'Drinks']);
        $food = Category::create(['name' => 'Food']);
        $this->makeProduct('D-1', 'Water', '5.000', $drinks);
        $this->makeProduct('F-1', 'Bread', '5.000', $food);

        $this->actingAs($this->user)->get("/stock?category_id={$food->id}")
            ->assertInertia(fn(Assert $page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.sku', 'F-1'));

        $this->actingAs($this->user)->get('/stock?search=water')
            ->assertInertia(fn(Assert $page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.sku', 'D-1'));
    }

    public function test_low_stock_lists_products_at_or_below_the_reorder_level(): void
    {
        $below = $this->makeProduct('LOW-1', 'Below', '5.000');
        $equal = $this->makeProduct('LOW-2', 'Equal', '5.000');
        $above = $this->makeProduct('OK-1', 'Above', '5.000');
        $never = $this->makeProduct('LOW-3', 'Never stocked', '5.000');
        $inactive = $this->makeProduct('OFF-1', 'Discontinued', '5.000', null, false);

        $this->stock->receive($below->id, $this->main->id, '4.999', $this->user->id);
        $this->stock->receive($equal->id, $this->main->id, '5', $this->user->id);
        $this->stock->receive($above->id, $this->main->id, '5.001', $this->user->id);
        // The total over ALL warehouses counts: 3 + 3 = 6 is above 5.
        $this->stock->receive($above->id, $this->branch->id, '3', $this->user->id);

        $this->actingAs($this->user)->get('/stock/low')
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('Inventory/Stock/LowStock')
                ->has('products.data', 3) // Below, Equal, Never stocked
                ->where('products.data.0.sku', 'LOW-1')
                ->where('products.data.1.sku', 'LOW-2')
                ->where('products.data.2.sku', 'LOW-3'));

        // The scope the dashboard will reuse gives the same count.
        $this->assertSame(3, Product::lowStock()->count());
    }

    public function test_stock_card_shows_a_running_balance_newest_first(): void
    {
        $water = $this->makeProduct('SKU-1', 'Water');
        $this->stock->setOpening($water->id, $this->main->id, '10', $this->user->id);
        $this->stock->deduct($water->id, $this->main->id, '3', $this->user->id);
        $this->stock->adjust($water->id, $this->main->id, '-1', 'Damaged', $this->user->id);

        $this->actingAs($this->user)->get("/stock/card/{$water->id}")
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('Inventory/Stock/Card')
                ->has('movements.data', 3)
                // Newest first: adjustment, sale, opening.
                ->where('movements.data.0.movement_type', 'adjustment')
                ->where('movements.data.0.running_balance', '6.000')
                ->where('movements.data.1.running_balance', '7.000')
                ->where('movements.data.2.running_balance', '10.000'));
    }

    public function test_filtering_the_card_by_type_keeps_the_true_balance(): void
    {
        $water = $this->makeProduct('SKU-1', 'Water');
        $this->stock->setOpening($water->id, $this->main->id, '10', $this->user->id);
        $this->stock->deduct($water->id, $this->main->id, '3', $this->user->id);

        // Only the sale is shown, but its balance still includes the opening stock: 7, not -3.
        $this->actingAs($this->user)->get("/stock/card/{$water->id}?type=sale")
            ->assertInertia(fn(Assert $page) => $page
                ->has('movements.data', 1)
                ->where('movements.data.0.movement_type', 'sale')
                ->where('movements.data.0.running_balance', '7.000'));
    }

    public function test_card_date_filter_uses_addis_ababa_business_days(): void
    {
        $water = $this->makeProduct('SKU-1', 'Water');
        $movement = $this->stock->setOpening($water->id, $this->main->id, '10', $this->user->id);

        // 22:00 UTC on 5 January is 01:00 on 6 January in Addis Ababa (UTC+3).
        StockMovement::whereKey($movement->id)->update(['created_at' => '2026-01-05 22:00:00']);

        $this->actingAs($this->user)->get("/stock/card/{$water->id}?from=2026-01-06&to=2026-01-06")
            ->assertInertia(fn(Assert $page) => $page->has('movements.data', 1));

        $this->actingAs($this->user)->get("/stock/card/{$water->id}?from=2026-01-05&to=2026-01-05")
            ->assertInertia(fn(Assert $page) => $page->has('movements.data', 0));
    }

    public function test_an_unknown_movement_type_filter_is_rejected(): void
    {
        $water = $this->makeProduct('SKU-1', 'Water');

        $this->actingAs($this->user)->get("/stock/card/{$water->id}?type=banana")
            ->assertSessionHasErrors('type');
    }
}
