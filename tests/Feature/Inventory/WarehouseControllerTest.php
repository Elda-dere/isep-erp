<?php

namespace Tests\Feature\Inventory;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WarehouseControllerTest extends TestCase
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

    public function test_user_without_permission_cannot_list_warehouses(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/warehouses')->assertForbidden();
    }

    public function test_user_with_inventory_view_can_list_warehouses(): void
    {
        Warehouse::create(['name' => 'Main store', 'location' => 'Addis Ababa']);
        $user = $this->userWith('inventory.view');

        $this->actingAs($user)->get('/warehouses')
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('Inventory/Warehouses/Index')
                ->has('warehouses.data', 1));
    }

    public function test_view_only_user_cannot_change_warehouses(): void
    {
        $warehouse = Warehouse::create(['name' => 'Main store']);
        $user = $this->userWith('inventory.view');

        $this->actingAs($user)->post('/warehouses', ['name' => 'New store'])->assertForbidden();
        $this->actingAs($user)->put("/warehouses/{$warehouse->id}", ['name' => 'Renamed'])->assertForbidden();

        $this->assertDatabaseMissing('warehouses', ['name' => 'New store']);
        $this->assertSame('Main store', $warehouse->fresh()->name);
    }

    public function test_manager_can_create_a_warehouse(): void
    {
        $user = $this->userWith('warehouses.manage');

        $this->actingAs($user)
            ->post('/warehouses', ['name' => 'New store', 'location' => 'Bole'])
            ->assertRedirect(route('warehouses.index'));

        $this->assertDatabaseHas('warehouses', ['name' => 'New store', 'location' => 'Bole']);
    }

    public function test_warehouse_name_is_required(): void
    {
        $user = $this->userWith('warehouses.manage');

        $this->actingAs($user)
            ->post('/warehouses', ['name' => '', 'location' => 'Bole'])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('warehouses', 0);
    }

    public function test_manager_can_update_a_warehouse(): void
    {
        $warehouse = Warehouse::create(['name' => 'Main store']);
        $user = $this->userWith('warehouses.manage');

        $this->actingAs($user)
            ->put("/warehouses/{$warehouse->id}", ['name' => 'Central store', 'location' => 'Piassa'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('warehouses.index'));

        $this->assertSame('Central store', $warehouse->fresh()->name);
        $this->assertSame('Piassa', $warehouse->fresh()->location);
    }

    public function test_location_is_optional(): void
    {
        $user = $this->userWith('warehouses.manage');

        $this->actingAs($user)
            ->post('/warehouses', ['name' => 'No location store'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('warehouses', ['name' => 'No location store', 'location' => null]);
    }
}
