<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoleAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $roleName)->firstOrFail()->id);

        return $user;
    }

    private function permissionIds(array $names): array
    {
        return Permission::whereIn('name', $names)->pluck('id')->all();
    }

    public function test_guests_and_users_without_the_permission_are_refused(): void
    {
        $this->get('/roles')->assertRedirect('/login');

        $this->actingAs($this->userWithRole('Sales Clerk'))->get('/roles')->assertForbidden();
    }

    public function test_admin_sees_the_seeded_roles(): void
    {
        $this->actingAs($this->userWithRole('Administrator'))->get('/roles')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Roles/Index')
                ->has('roles', 8));
    }

    public function test_admin_can_create_a_role_with_permissions(): void
    {
        $ids = $this->permissionIds(['audit.view', 'reports.view']);

        $this->actingAs($this->userWithRole('Administrator'))->post('/roles', [
            'name' => 'Auditor',
            'permissions' => $ids,
        ])->assertRedirect(route('roles.index'));

        $role = Role::where('name', 'Auditor')->firstOrFail();

        $this->assertSame(2, $role->permissions()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.created', 'entity_id' => $role->id]);
    }

    public function test_a_duplicate_role_name_is_rejected(): void
    {
        $this->actingAs($this->userWithRole('Administrator'))
            ->post('/roles', ['name' => 'HR Manager'])
            ->assertSessionHasErrors('name');
    }

    public function test_update_renames_the_role_and_syncs_permissions(): void
    {
        $role = Role::create(['name' => 'Temp Role']);
        $role->permissions()->attach($this->permissionIds(['reports.view']));

        $this->actingAs($this->userWithRole('Administrator'))->put("/roles/{$role->id}", [
            'name' => 'Report Reader',
            'permissions' => $this->permissionIds(['dashboard.view']),
        ])->assertRedirect(route('roles.index'));

        $role->refresh();

        $this->assertSame('Report Reader', $role->name);
        $this->assertSame(['dashboard.view'], $role->permissions()->pluck('name')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.updated', 'entity_id' => $role->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.permissions_changed', 'entity_id' => $role->id]);
    }

    public function test_the_administrator_role_is_locked(): void
    {
        $admin = $this->userWithRole('Administrator');
        $role = Role::where('name', 'Administrator')->firstOrFail();

        $this->actingAs($admin)->put("/roles/{$role->id}", [
            'name' => 'Renamed',
            'permissions' => [],
        ])->assertSessionHasErrors('name');

        $role->refresh();

        $this->assertSame('Administrator', $role->name);
        $this->assertSame(count(PermissionSeeder::NAMES), $role->permissions()->count());
    }

    public function test_an_invalid_permission_id_is_rejected(): void
    {
        $this->actingAs($this->userWithRole('Administrator'))->post('/roles', [
            'name' => 'Broken',
            'permissions' => [999999],
        ])->assertSessionHasErrors('permissions.0');

        $this->assertDatabaseMissing('roles', ['name' => 'Broken']);
    }

    public function test_a_change_reaches_the_people_in_the_role_immediately(): void
    {
        $clerk = $this->userWithRole('Sales Clerk');
        $role = Role::where('name', 'Sales Clerk')->firstOrFail();

        $this->assertFalse($clerk->hasPermission('audit.view'));

        $ids = $role->permissions()->pluck('permissions.id')
            ->merge($this->permissionIds(['audit.view']))
            ->all();

        $this->actingAs($this->userWithRole('Administrator'))->put("/roles/{$role->id}", [
            'name' => 'Sales Clerk',
            'permissions' => $ids,
        ])->assertSessionHasNoErrors();

        $this->assertTrue($clerk->fresh()->hasPermission('audit.view'));
    }

    public function test_no_route_can_delete_a_role(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'roles')) {
                $this->assertNotContains('DELETE', $route->methods());
            }
        }
    }
}