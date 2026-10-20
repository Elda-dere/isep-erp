<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedWithAdmin(): void
    {
        config([
            'erp.admin.email' => 'admin@example.test',
            'erp.admin.password' => 'a-long-test-password',
        ]);

        $this->seed();
    }

    public function test_default_roles_and_permissions_are_created(): void
    {
        $this->seedWithAdmin();

        $this->assertSame(8, Role::count());
        $this->assertSame(count(PermissionSeeder::NAMES), Permission::count());
    }

    public function test_every_role_gets_exactly_its_listed_permissions(): void
    {
        $this->seedWithAdmin();

        foreach (RoleSeeder::MAP as $roleName => $names) {
            $role = Role::where('name', $roleName)->firstOrFail();

            $this->assertSame(
                count(array_unique($names)),
                $role->permissions()->count(),
                "Role {$roleName} is missing a permission (check for a typo in the name)."
            );
        }
    }

    public function test_admin_can_do_everything_and_the_password_is_hashed(): void
    {
        $this->seedWithAdmin();

        $admin = User::where('email', 'admin@example.test')->firstOrFail();

        $this->assertNotSame('a-long-test-password', $admin->password);

        foreach (PermissionSeeder::NAMES as $name) {
            $this->assertTrue($admin->hasPermission($name), "Admin lacks {$name}");
        }
    }

    public function test_scanner_role_can_only_scan(): void
    {
        $this->seedWithAdmin();

        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'Attendance Scanner')->firstOrFail());

        $this->assertTrue($user->hasPermission('attendance.scan'));
        $this->assertFalse($user->hasPermission('employees.manage'));
        $this->assertFalse($user->hasPermission('attendance.correct'));
    }

    public function test_seeding_twice_creates_no_duplicates(): void
    {
        $this->seedWithAdmin();
        $this->seed();

        $this->assertSame(8, Role::count());
        $this->assertSame(1, User::count());
        $this->assertSame(1, DB::table('customers')->where('name', 'Walk-in customer')->count());
        $this->assertSame(8, DB::table('units')->count());
    }

    public function test_seeding_fails_without_an_admin_password(): void
    {
        config(['erp.admin.email' => 'admin@example.test', 'erp.admin.password' => null]);

        $this->expectException(RuntimeException::class);

        $this->seed();
    }

    public function test_seeding_rejects_a_short_admin_password(): void
    {
        config(['erp.admin.email' => 'admin@example.test', 'erp.admin.password' => 'short']);

        $this->expectException(RuntimeException::class);

        $this->seed();
    }
}