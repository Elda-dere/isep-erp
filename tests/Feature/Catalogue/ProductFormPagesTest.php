<?php

namespace Tests\Feature\Catalogue;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductFormPagesTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

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

    public function test_a_view_only_user_cannot_open_the_forms(): void
    {
        $viewer = $this->userWith('products.view');

        $this->actingAs($viewer)->get('/products/create')->assertForbidden();
        $this->actingAs($viewer)->get("/products/{$this->product->id}/edit")->assertForbidden();
    }

    public function test_a_manager_sees_the_empty_form_with_categories_and_units(): void
    {
        $manager = $this->userWith('products.manage');

        $this->actingAs($manager)->get('/products/create')
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('Catalogue/Products/Form')
                ->where('product', null)
                ->has('categories', 1)
                ->has('units', 1));
    }

    public function test_a_manager_sees_the_edit_form_with_exact_values(): void
    {
        $manager = $this->userWith('products.manage');

        $this->actingAs($manager)->get("/products/{$this->product->id}/edit")
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('Catalogue/Products/Form')
                ->where('product.id', $this->product->id)
                ->where('product.sku', 'SKU-001')
                ->where('product.cost_price', '10.00')   // exact string, not a float
                ->where('product.reorder_level', '5.000')
                ->where('product.is_active', true));
    }

    public function test_editing_a_missing_product_returns_404(): void
    {
        $manager = $this->userWith('products.manage');

        $this->actingAs($manager)->get('/products/99999/edit')->assertNotFound();
    }
}
