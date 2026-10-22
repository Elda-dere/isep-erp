<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SharedPropsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_gets_no_user_and_no_permissions(): void
    {
        $this->withoutVite();

        $this->get('/login')->assertInertia(fn (Assert $page) => $page
            ->where('auth.user', null)
            ->where('auth.permissions', [])
        );
    }

    public function test_signed_in_user_gets_their_name_and_permissions(): void
    {
        $this->withoutVite();

        $user = User::factory()->create();
        $role = Role::create(['name' => 'Test Role']);
        $role->permissions()->attach([
            Permission::create(['name' => 'sales.view'])->id,
            Permission::create(['name' => 'employees.view'])->id,
        ]);
        $user->roles()->attach($role);

        $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.email', $user->email)
            ->where('auth.permissions', ['employees.view', 'sales.view'])
        );
    }
}