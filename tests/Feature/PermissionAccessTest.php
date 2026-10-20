<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PermissionAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A temporary door that only people with employees.view may open.
        Route::middleware(['web', 'auth', 'permission:employees.view'])
            ->get('/_test/employees', fn () => 'ok');
    }

    private function userWith(string $permission, bool $active = true): User
    {
        $user = User::factory()->create(['is_active' => $active]);
        $role = Role::firstOrCreate(['name' => 'Test Role']);
        $role->permissions()->syncWithoutDetaching([
            Permission::firstOrCreate(['name' => $permission])->id,
        ]);
        $user->roles()->attach($role);

        return $user;
    }

    public function test_guest_is_rejected(): void
    {
        $this->getJson('/_test/employees')->assertUnauthorized();
    }

    public function test_user_without_the_permission_gets_403(): void
    {
        $user = $this->userWith('sales.view');

        $this->actingAs($user)->get('/_test/employees')->assertForbidden();
    }

    public function test_user_with_the_permission_gets_in(): void
    {
        $user = $this->userWith('employees.view');

        $this->actingAs($user)->get('/_test/employees')->assertOk();
    }

    public function test_inactive_user_is_signed_out_on_the_next_request(): void
    {
        $user = $this->userWith('employees.view', active: false);

        $this->actingAs($user)
            ->get('/_test/employees')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_laravel_gate_follows_our_permission_tables(): void
    {
        $user = $this->userWith('employees.view');

        $this->assertTrue($user->can('employees.view'));
        $this->assertFalse($user->can('employees.manage'));
    }
}