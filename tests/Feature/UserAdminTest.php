<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserAdminTest extends TestCase
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

    /** May manage users but is NOT an Administrator. */
    private function userManager(): User
    {
        $role = Role::create(['name' => 'User Manager']);
        $role->permissions()->attach(Permission::whereIn('name', ['users.view', 'users.manage'])->pluck('id')->all());

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    /** May only look at the list. */
    private function userViewer(): User
    {
        $role = Role::create(['name' => 'User Viewer']);
        $role->permissions()->attach(Permission::where('name', 'users.view')->pluck('id')->all());

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_guest_is_sent_to_the_login_page(): void
    {
        $this->get('/users')->assertRedirect('/login');
    }

    public function test_user_without_the_permission_gets_403(): void
    {
        $this->actingAs($this->userWithRole('Employee'))->get('/users')->assertForbidden();
    }

    public function test_a_viewer_can_list_but_cannot_change_anything(): void
    {
        $viewer = $this->userViewer();
        $target = User::factory()->create();

        $this->actingAs($viewer)->get('/users')->assertOk();
        $this->actingAs($viewer)->get('/users/create')->assertForbidden();
        $this->actingAs($viewer)->post('/users', [])->assertForbidden();
        $this->actingAs($viewer)->patch("/users/{$target->id}/deactivate")->assertForbidden();
    }

    public function test_admin_sees_the_list(): void
    {
        $admin = $this->userWithRole('Administrator');
        User::factory()->count(2)->create();

        $this->actingAs($admin)->get('/users')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Users/Index')
                ->where('users.total', 3));
    }

    public function test_the_list_can_be_searched(): void
    {
        $admin = $this->userWithRole('Administrator');
        User::factory()->create(['name' => 'Ana Tesfaye']);
        User::factory()->create(['name' => 'Bekele Alemu']);

        $this->actingAs($admin)->get('/users?search=tesfaye')
            ->assertInertia(fn (Assert $page) => $page->where('users.total', 1));
    }

    public function test_admin_can_create_a_user_with_roles(): void
    {
        $admin = $this->userWithRole('Administrator');
        $hr = Role::where('name', 'HR Manager')->firstOrFail();

        $this->actingAs($admin)->post('/users', [
            'name' => 'Ana Tesfaye',
            'email' => 'ana@example.test',
            'password' => 'long-password-123',
            'password_confirmation' => 'long-password-123',
            'roles' => [$hr->id],
        ])->assertRedirect(route('users.index'));

        $ana = User::where('email', 'ana@example.test')->firstOrFail();

        $this->assertTrue(Hash::check('long-password-123', $ana->password));
        $this->assertTrue($ana->roles()->where('roles.id', $hr->id)->exists());
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.created', 'entity_id' => $ana->id]);
        $this->assertStringNotContainsString('long-password-123', json_encode(AuditLog::all()->toArray()));
    }

    public function test_a_duplicate_email_is_rejected(): void
    {
        $admin = $this->userWithRole('Administrator');
        User::factory()->create(['email' => 'taken@example.test']);

        $this->actingAs($admin)->post('/users', [
            'name' => 'Another',
            'email' => 'taken@example.test',
            'password' => 'long-password-123',
            'password_confirmation' => 'long-password-123',
        ])->assertSessionHasErrors('email');
    }

    public function test_a_short_password_is_rejected(): void
    {
        $admin = $this->userWithRole('Administrator');

        $this->actingAs($admin)->post('/users', [
            'name' => 'Short',
            'email' => 'short@example.test',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'short@example.test']);
    }

    public function test_update_changes_the_name_and_roles_and_is_audited(): void
    {
        $manager = $this->userManager();
        $hr = Role::where('name', 'HR Manager')->firstOrFail();
        $target = User::factory()->create(['name' => 'Old Name']);

        $this->actingAs($manager)->put("/users/{$target->id}", [
            'name' => 'New Name',
            'email' => $target->email,
            'roles' => [$hr->id],
        ])->assertRedirect(route('users.index'));

        $this->assertSame('New Name', $target->fresh()->name);
        $this->assertTrue($target->roles()->where('roles.id', $hr->id)->exists());
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.updated', 'entity_id' => $target->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.roles_changed', 'entity_id' => $target->id]);
    }

    public function test_an_administrator_can_be_deactivated_when_another_exists(): void
    {
        $actor = $this->userWithRole('Administrator');
        $other = $this->userWithRole('Administrator');

        $this->actingAs($actor)->patch("/users/{$other->id}/deactivate")->assertSessionHasNoErrors();

        $this->assertFalse($other->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.deactivated', 'entity_id' => $other->id]);
    }

    public function test_you_cannot_deactivate_yourself(): void
    {
        $admin = $this->userWithRole('Administrator');

        $this->actingAs($admin)->patch("/users/{$admin->id}/deactivate")->assertSessionHasErrors('user');

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_the_last_active_administrator_cannot_be_deactivated(): void
    {
        $manager = $this->userManager();
        $sole = $this->userWithRole('Administrator');

        $this->actingAs($manager)->patch("/users/{$sole->id}/deactivate")->assertSessionHasErrors('user');

        $this->assertTrue($sole->fresh()->is_active);
    }

    public function test_the_last_administrator_cannot_lose_the_administrator_role(): void
    {
        $manager = $this->userManager();
        $sole = $this->userWithRole('Administrator');

        $this->actingAs($manager)->put("/users/{$sole->id}", [
            'name' => $sole->name,
            'email' => $sole->email,
            'roles' => [],
        ])->assertSessionHasErrors('roles');

        $this->assertTrue($sole->roles()->where('name', 'Administrator')->exists());
    }

    public function test_an_inactive_user_can_be_activated(): void
    {
        $manager = $this->userManager();
        $target = User::factory()->inactive()->create();

        $this->actingAs($manager)->patch("/users/{$target->id}/activate")->assertSessionHasNoErrors();

        $this->assertTrue($target->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.activated', 'entity_id' => $target->id]);
    }

    public function test_a_new_password_can_be_set_and_is_never_logged(): void
    {
        $manager = $this->userManager();
        $target = User::factory()->create(['password' => 'old-password-123']);

        $this->actingAs($manager)->put("/users/{$target->id}/password", [
            'password' => 'brand-new-pass-1',
            'password_confirmation' => 'brand-new-pass-1',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('brand-new-pass-1', $target->fresh()->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.password_reset', 'entity_id' => $target->id]);
        $this->assertStringNotContainsString('brand-new-pass-1', json_encode(AuditLog::all()->toArray()));
    }
}