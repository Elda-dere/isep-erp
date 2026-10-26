<?php

namespace Tests\Feature\Inventory;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WarehouseFormPagesTest extends TestCase
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

    public function test_a_view_only_user_cannot_open_the_forms(): void
    {
        $warehouse = Warehouse::create(['name' => 'Main store']);
        $viewer = $this->userWith('inventory.view');

        $this->actingAs($viewer)->get('/warehouses/create')->assertForbidden();
        $this->actingAs($viewer)->get("/warehouses/{$warehouse->id}/edit")->assertForbidden();
    }

    public function test_a_manager_sees_the_empty_form(): void
    {
        $manager = $this->userWith('warehouses.manage');

        $this->actingAs($manager)->get('/warehouses/create')
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('Inventory/Warehouses/Form')
                ->where('warehouse', null));
    }

    public function test_a_manager_sees_the_edit_form_with_current_values(): void
    {
        $warehouse = Warehouse::create(['name' => 'Main store', 'location' => 'Bole']);
        $manager = $this->userWith('warehouses.manage');

        $this->actingAs($manager)->get("/warehouses/{$warehouse->id}/edit")
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('Inventory/Warehouses/Form')
                ->where('warehouse.id', $warehouse->id)
                ->where('warehouse.name', 'Main store')
                ->where('warehouse.location', 'Bole'));
    }

    public function test_editing_a_missing_warehouse_returns_404(): void
    {
        $manager = $this->userWith('warehouses.manage');

        $this->actingAs($manager)->get('/warehouses/99999/edit')->assertNotFound();
    }
}
