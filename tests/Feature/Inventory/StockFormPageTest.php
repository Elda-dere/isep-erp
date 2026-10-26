<?php

namespace Tests\Feature\Inventory;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StockFormPageTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;
    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); // no compiled frontend files needed in tests

        $this->warehouse = Warehouse::create(['name' => 'Main store']);
        $this->product = Product::create([
            'category_id' => Category::create(['name' => 'Beverages'])->id,
            'unit_id' => Unit::create(['name' => 'Piece', 'symbol' => 'pcs'])->id,
            'sku' => 'SKU-001',
            'name' => 'Water 1L',
            'cost_price' => '10.00',
            'selling_price' => '15.00',
            'reorder_level' => '5.000',
            'is_active' => true,
        ]);
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

    public function test_a_view_only_user_cannot_open_the_form(): void
    {
        $viewer = $this->userWith('inventory.view');

        $this->actingAs($viewer)->get('/stock/adjust')->assertForbidden();
    }

    public function test_a_user_who_may_adjust_sees_the_products_and_warehouses(): void
    {
        $clerk = $this->userWith('inventory.adjust');

        $this->actingAs($clerk)->get('/stock/adjust')
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('Inventory/Stock/Adjust')
                ->has('products', 1)
                ->where('products.0.sku', 'SKU-001')
                ->where('products.0.unit.symbol', 'pcs')
                ->has('warehouses', 1)
                ->where('selected.product_id', null));
    }

    public function test_the_form_can_open_with_a_product_already_chosen(): void
    {
        $clerk = $this->userWith('inventory.adjust');

        $this->actingAs($clerk)->get("/stock/adjust?product_id={$this->product->id}")
            ->assertInertia(fn(Assert $page) => $page
                ->where('selected.product_id', $this->product->id));
    }
}
