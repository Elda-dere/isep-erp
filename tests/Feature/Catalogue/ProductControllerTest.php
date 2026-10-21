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

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
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

    private function productData(array $overrides = []): array
    {
        $category = Category::firstOrCreate(['name' => 'Beverages']);
        $unit = Unit::firstOrCreate(['name' => 'Piece', 'symbol' => 'pcs']);

        return array_merge([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'sku' => 'SKU-001',
            'name' => 'Water 1L',
            'description' => null,
            'cost_price' => '10.50',
            'selling_price' => '15.00',
            'reorder_level' => '5.250',
            'is_active' => true,
        ], $overrides);
    }

    private function makeProduct(array $overrides = []): Product
    {
        return Product::create($this->productData($overrides));
    }

    public function test_user_without_permission_cannot_list_products(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/products')->assertForbidden();
    }

    public function test_user_with_view_permission_can_list_products(): void
    {
        $this->makeProduct();
        $user = $this->userWith('products.view');

        $this->actingAs($user)->get('/products')
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('Catalogue/Products/Index')
                ->has('products.data', 1));
    }

    public function test_view_only_user_cannot_change_products(): void
    {
        $product = $this->makeProduct();
        $user = $this->userWith('products.view');

        $this->actingAs($user)->post('/products', $this->productData(['sku' => 'NEW-1']))->assertForbidden();
        $this->actingAs($user)->put("/products/{$product->id}", $this->productData())->assertForbidden();
        $this->actingAs($user)->patch("/products/{$product->id}/deactivate")->assertForbidden();

        $this->assertDatabaseMissing('products', ['sku' => 'NEW-1']);
        $this->assertTrue($product->fresh()->is_active);
    }

    public function test_manager_can_create_a_product(): void
    {
        $user = $this->userWith('products.manage');

        $this->actingAs($user)
            ->post('/products', $this->productData(['sku' => 'NEW-1']))
            ->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', ['sku' => 'NEW-1', 'name' => 'Water 1L']);
    }

    public function test_invalid_product_is_rejected(): void
    {
        $user = $this->userWith('products.manage');

        $this->actingAs($user)
            ->post('/products', $this->productData(['sku' => '']))
            ->assertSessionHasErrors('sku');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_update_can_keep_the_same_sku(): void
    {
        $product = $this->makeProduct();
        $user = $this->userWith('products.manage');

        $this->actingAs($user)
            ->put("/products/{$product->id}", $this->productData(['name' => 'Water 2L']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('products.index'));

        $this->assertSame('Water 2L', $product->fresh()->name);
    }

    public function test_product_can_be_deactivated_and_activated(): void
    {
        $product = $this->makeProduct();
        $user = $this->userWith('products.manage');

        $this->actingAs($user)->patch("/products/{$product->id}/deactivate");
        $this->assertFalse($product->fresh()->is_active);

        $this->actingAs($user)->patch("/products/{$product->id}/activate");
        $this->assertTrue($product->fresh()->is_active);
    }

    public function test_products_can_be_searched_by_name_or_sku(): void
    {
        $this->makeProduct(['sku' => 'SKU-001', 'name' => 'Water 1L']);
        $this->makeProduct(['sku' => 'JUICE-9', 'name' => 'Orange juice']);
        $user = $this->userWith('products.view');

        $this->actingAs($user)->get('/products?search=water')
            ->assertInertia(fn(Assert $page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.sku', 'SKU-001'));

        $this->actingAs($user)->get('/products?search=JUICE')
            ->assertInertia(fn(Assert $page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.sku', 'JUICE-9'));
    }

    public function test_products_can_be_filtered_by_category_and_status(): void
    {
        $drinks = Category::create(['name' => 'Drinks']);
        $food = Category::create(['name' => 'Food']);
        $this->makeProduct(['sku' => 'D-1', 'category_id' => $drinks->id]);
        $this->makeProduct(['sku' => 'F-1', 'category_id' => $food->id, 'is_active' => false]);
        $user = $this->userWith('products.view');

        $this->actingAs($user)->get("/products?category_id={$food->id}")
            ->assertInertia(fn(Assert $page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.sku', 'F-1'));

        $this->actingAs($user)->get('/products?active=1')
            ->assertInertia(fn(Assert $page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.sku', 'D-1'));

        $this->actingAs($user)->get('/products?active=0')
            ->assertInertia(fn(Assert $page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.sku', 'F-1'));
    }
}
