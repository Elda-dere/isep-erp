<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermission(string $permission, bool $active = true): User
    {
        $user = User::factory()->create(['is_active' => $active]);
        $role = Role::create(['name' => 'Test Role']);
        $role->permissions()->attach(Permission::create(['name' => $permission]));
        $user->roles()->attach($role);

        return $user;
    }

    public function test_user_has_permission_through_a_role(): void
    {
        $user = $this->userWithPermission('employees.view');

        $this->assertTrue($user->hasPermission('employees.view'));
    }

    public function test_user_without_the_permission_is_rejected(): void
    {
        $user = $this->userWithPermission('employees.view');

        $this->assertFalse($user->hasPermission('employees.delete'));
    }

    public function test_user_without_roles_has_no_permissions(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->hasPermission('employees.view'));
    }

    public function test_inactive_user_has_no_permissions(): void
    {
        $user = $this->userWithPermission('employees.view', active: false);

        $this->assertFalse($user->hasPermission('employees.view'));
    }

    public function test_password_is_stored_hashed(): void
    {
        $user = User::factory()->create(['password' => 'secret-password-1']);

        $this->assertNotSame('secret-password-1', $user->password);
    }
}