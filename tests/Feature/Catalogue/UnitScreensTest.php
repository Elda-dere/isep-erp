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

class UnitScreensTest extends TestCase
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

    // Puts one product on the unit, so the unit is "in use".
    private function useUnit(Unit $unit): Product
    {
        return Product::create([
            'category_id' => Category::firstOrCreate(['name' => 'Beverages'])->id,
            'unit_id' => $unit->id,
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

    private function values(object $row, string $column): ?array
    {
        return $row->{$column} === null ? null : json_decode($row->{$column}, true);
    }

    public function test_a_view_only_user_cannot_use_the_manage_routes(): void
    {
        $unit = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg']);
        $viewer = $this->userWith('products.view');

        $this->actingAs($viewer)->get('/units/create')->assertForbidden();
        $this->actingAs($viewer)->get("/units/{$unit->id}/edit")->assertForbidden();
        $this->actingAs($viewer)->post('/units', ['name' => 'Litre', 'symbol' => 'l'])->assertForbidden();
        $this->actingAs($viewer)->put("/units/{$unit->id}", ['name' => 'Renamed', 'symbol' => 'x'])->assertForbidden();
        $this->actingAs($viewer)->delete("/units/{$unit->id}")->assertForbidden();

        $this->assertDatabaseMissing('units', ['name' => 'Litre']);
        $this->assertSame('Kilogram', $unit->fresh()->name);
    }

    public function test_the_list_shows_units_with_product_counts(): void
    {
        $kilogram = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg']);
        Unit::create(['name' => 'Piece', 'symbol' => 'pcs']);
        $this->useUnit($kilogram);
        $viewer = $this->userWith('products.view');

        $this->actingAs($viewer)->get('/units')
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('Catalogue/Units/Index')
                ->has('units.data', 2)
                ->where('units.data.0.name', 'Kilogram')
                ->where('units.data.0.products_count', 1)
                ->where('units.data.1.name', 'Piece')
                ->where('units.data.1.products_count', 0));
    }

    public function test_creating_a_unit_writes_an_audit_row(): void
    {
        $manager = $this->userWith('products.manage');

        $this->actingAs($manager)
            ->post('/units', ['name' => 'Litre', 'symbol' => 'l'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('units.index'));

        $unit = Unit::where('name', 'Litre')->first();
        $rows = $this->audit('unit.created');

        $this->assertCount(1, $rows);
        $this->assertSame($manager->id, (int) $rows->first()->user_id);
        $this->assertSame($unit->id, (int) $rows->first()->entity_id);
        $this->assertSame('l', $this->values($rows->first(), 'new_values')['symbol']);
    }

    public function test_the_name_and_symbol_are_required(): void
    {
        $manager = $this->userWith('products.manage');

        $this->actingAs($manager)->post('/units', ['name' => '', 'symbol' => ''])
            ->assertSessionHasErrors(['name', 'symbol']);

        $this->assertDatabaseCount('units', 0);
    }

    public function test_the_edit_form_shows_the_current_values(): void
    {
        $unit = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg']);
        $manager = $this->userWith('products.manage');

        $this->actingAs($manager)->get("/units/{$unit->id}/edit")
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('Catalogue/Units/Form')
                ->where('unit.id', $unit->id)
                ->where('unit.name', 'Kilogram')
                ->where('unit.symbol', 'kg'));
    }

    public function test_an_update_logs_only_the_changed_fields(): void
    {
        $unit = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg']);
        $manager = $this->userWith('products.manage');

        $this->actingAs($manager)
            ->put("/units/{$unit->id}", ['name' => 'Kilogram', 'symbol' => 'KG'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('units.index'));

        $rows = $this->audit('unit.updated');
        $this->assertCount(1, $rows);
        $this->assertSame(['symbol' => 'kg'], $this->values($rows->first(), 'old_values'));
        $this->assertSame(['symbol' => 'KG'], $this->values($rows->first(), 'new_values'));
    }

    public function test_an_unused_unit_can_be_deleted_and_the_deletion_is_logged(): void
    {
        $unit = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg']);
        $manager = $this->userWith('products.manage');

        $this->actingAs($manager)->delete("/units/{$unit->id}")
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('units.index'));

        $this->assertDatabaseMissing('units', ['id' => $unit->id]);

        $rows = $this->audit('unit.deleted');
        $this->assertCount(1, $rows);
        $this->assertSame('Kilogram', $this->values($rows->first(), 'old_values')['name']);
    }

    public function test_a_unit_used_by_products_cannot_be_deleted(): void
    {
        $unit = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg']);
        $this->useUnit($unit);
        $manager = $this->userWith('products.manage');

        $this->actingAs($manager)->delete("/units/{$unit->id}")
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('units', ['id' => $unit->id]);
        $this->assertCount(0, $this->audit('unit.deleted')); // nothing happened, so nothing is logged
    }
}
