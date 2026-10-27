<?php

namespace Tests\Feature\Catalogue;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CategoryScreensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); // no compiled frontend files needed in tests
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

    // Puts one product in the category, so the category is "in use".
    private function useCategory(Category $category): Product
    {
        return Product::create([
            'category_id' => $category->id,
            'unit_id' => Unit::firstOrCreate(['name' => 'Piece', 'symbol' => 'pcs'])->id,
            'sku' => 'SKU-' . uniqid(),
            'name' => 'Water 1L',
            'cost_price' => '10.00',
            'selling_price' => '15.00',
            'reorder_level' => '5.000',
            'is_active' => true,
        ]);
    }

    private function audit(string $action)
    {
        return DB::table('audit_logs')->where('action', $action)->get();
    }

    // The JSON columns come back as text: turn them into arrays.
    private function values(object $row, string $column): ?array
    {
        return $row->{$column} === null ? null : json_decode($row->{$column}, true);
    }

    public function test_a_view_only_user_cannot_use_the_manage_routes(): void
    {
        $category = Category::create(['name' => 'Beverages']);
        $viewer = $this->userWith('products.view');

        $this->actingAs($viewer)->get('/categories/create')->assertForbidden();
        $this->actingAs($viewer)->get("/categories/{$category->id}/edit")->assertForbidden();
        $this->actingAs($viewer)->post('/categories', ['name' => 'New'])->assertForbidden();
        $this->actingAs($viewer)->put("/categories/{$category->id}", ['name' => 'Renamed'])->assertForbidden();
        $this->actingAs($viewer)->delete("/categories/{$category->id}")->assertForbidden();

        $this->assertDatabaseMissing('categories', ['name' => 'New']);
        $this->assertSame('Beverages', $category->fresh()->name);
    }

    public function test_the_list_shows_categories_with_product_counts(): void
    {
        $beverages = Category::create(['name' => 'Beverages']);
        Category::create(['name' => 'Food']);
        $this->useCategory($beverages);
        $viewer = $this->userWith('products.view');

        $this->actingAs($viewer)->get('/categories')
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('Catalogue/Categories/Index')
                ->has('categories.data', 2)
                ->where('categories.data.0.name', 'Beverages')
                ->where('categories.data.0.products_count', 1)
                ->where('categories.data.1.name', 'Food')
                ->where('categories.data.1.products_count', 0));
    }

    public function test_creating_a_category_writes_an_audit_row(): void
    {
        $manager = $this->userWith('products.manage');

        $this->actingAs($manager)
            ->post('/categories', ['name' => 'Snacks', 'description' => 'Dry goods'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('categories.index'));

        $category = Category::where('name', 'Snacks')->first();
        $rows = $this->audit('category.created');

        $this->assertCount(1, $rows);
        $this->assertSame($manager->id, (int) $rows->first()->user_id);
        $this->assertSame($category->id, (int) $rows->first()->entity_id);
        $this->assertSame('Dry goods', $this->values($rows->first(), 'new_values')['description']);
    }

    public function test_the_name_is_required_and_the_description_is_optional(): void
    {
        $manager = $this->userWith('products.manage');

        $this->actingAs($manager)->post('/categories', ['name' => ''])->assertSessionHasErrors('name');
        $this->assertDatabaseCount('categories', 0);

        $this->actingAs($manager)->post('/categories', ['name' => 'No description'])->assertSessionHasNoErrors();
        $this->assertNull(Category::where('name', 'No description')->first()->description);
    }

    public function test_the_edit_form_shows_the_current_values(): void
    {
        $category = Category::create(['name' => 'Beverages', 'description' => 'Drinks']);
        $manager = $this->userWith('products.manage');

        $this->actingAs($manager)->get("/categories/{$category->id}/edit")
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('Catalogue/Categories/Form')
                ->where('category.id', $category->id)
                ->where('category.name', 'Beverages')
                ->where('category.description', 'Drinks'));
    }

    public function test_an_update_logs_only_the_changed_fields(): void
    {
        $category = Category::create(['name' => 'Beverages', 'description' => 'Drinks']);
        $manager = $this->userWith('products.manage');

        $this->actingAs($manager)
            ->put("/categories/{$category->id}", ['name' => 'Soft drinks', 'description' => 'Drinks'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('categories.index'));

        $rows = $this->audit('category.updated');
        $this->assertCount(1, $rows);
        $this->assertSame(['name' => 'Beverages'], $this->values($rows->first(), 'old_values'));
        $this->assertSame(['name' => 'Soft drinks'], $this->values($rows->first(), 'new_values'));
    }

    public function test_an_unused_category_can_be_deleted_and_the_deletion_is_logged(): void
    {
        $category = Category::create(['name' => 'Beverages']);
        $manager = $this->userWith('products.manage');

        $this->actingAs($manager)->delete("/categories/{$category->id}")
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('categories.index'));

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);

        $rows = $this->audit('category.deleted');
        $this->assertCount(1, $rows);
        $this->assertSame('Beverages', $this->values($rows->first(), 'old_values')['name']);
    }

    public function test_a_category_used_by_products_cannot_be_deleted(): void
    {
        $category = Category::create(['name' => 'Beverages']);
        $this->useCategory($category);
        $manager = $this->userWith('products.manage');

        $this->actingAs($manager)->delete("/categories/{$category->id}")
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertCount(0, $this->audit('category.deleted')); // nothing happened, so nothing is logged
    }
}
