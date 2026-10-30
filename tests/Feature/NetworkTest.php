<?php

namespace Tests\Feature;

use App\Models\ApprovedNetwork;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NetworkTest extends TestCase
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

    private function admin(): User
    {
        return $this->userWithRole('Administrator');
    }

    private function network(array $overrides = []): ApprovedNetwork
    {
        return ApprovedNetwork::create(array_merge([
            'name' => 'Office',
            'ip_range' => '192.168.1.0/24',
            'is_active' => true,
        ], $overrides));
    }

    public function test_guests_and_users_without_the_permission_are_refused(): void
    {
        $this->get('/networks')->assertRedirect('/login');

        foreach (['Sales Clerk', 'HR Manager', 'Attendance Scanner'] as $roleName) {
            $user = $this->userWithRole($roleName);

            $this->actingAs($user)->get('/networks')->assertForbidden();
            $this->actingAs($user)->post('/networks', ['name' => 'X', 'ip_range' => '10.0.0.0/8'])->assertForbidden();
        }

        $this->assertSame(0, ApprovedNetwork::count());
    }

    public function test_the_page_warns_when_no_network_is_active(): void
    {
        $this->network(['is_active' => false]);

        $this->actingAs($this->admin())->get('/networks')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Networks/Index')
                ->where('networks.total', 1)
                ->where('hasActive', false)
                ->where('yourIp', '127.0.0.1')
                ->where('yourIpAllowed', false));
    }

    public function test_the_page_says_whether_your_address_is_inside_an_active_network(): void
    {
        $this->network(['name' => 'Local', 'ip_range' => '127.0.0.1/32']);

        $this->actingAs($this->admin())->get('/networks')
            ->assertInertia(fn (Assert $page) => $page
                ->where('hasActive', true)
                ->where('yourIpAllowed', true));
    }

    public function test_creating_a_network_saves_the_range_from_its_first_address_and_is_audited(): void
    {
        $this->actingAs($this->admin())->post('/networks', [
            'name' => 'Head office',
            'ip_range' => ' 192.168.1.77/24 ',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('approved_networks', [
            'name' => 'Head office',
            'ip_range' => '192.168.1.0/24',
            'is_active' => true,
        ]);

        $row = AuditLog::where('action', 'network.created')->firstOrFail();

        $this->assertSame('192.168.1.0/24', $row->new_values['ip_range']);
    }

    public function test_an_ipv6_range_is_saved_in_lowercase(): void
    {
        $this->actingAs($this->admin())->post('/networks', [
            'name' => 'IPv6 office',
            'ip_range' => '2001:DB8::/32',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('approved_networks', ['ip_range' => '2001:db8::/32']);
    }

    public function test_invalid_ranges_are_rejected(): void
    {
        $admin = $this->admin();

        foreach (['192.168.1.0', '192.168.1.0/33', 'abc', '10.0.0.0/8/1', '2001:db8::/129'] as $range) {
            $this->actingAs($admin)->post('/networks', ['name' => 'Bad', 'ip_range' => $range])
                ->assertSessionHasErrors('ip_range');
        }

        $this->assertSame(0, ApprovedNetwork::count());
    }

    public function test_ranges_that_allow_the_whole_internet_are_rejected(): void
    {
        $admin = $this->admin();

        foreach (['0.0.0.0/0', '::/0', '10.0.0.0/0'] as $range) {
            $this->actingAs($admin)->post('/networks', ['name' => 'Open', 'ip_range' => $range])
                ->assertSessionHasErrors('ip_range');
        }

        $this->assertSame(0, ApprovedNetwork::count());
    }

    public function test_a_name_is_required_and_has_a_limit(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/networks', ['name' => '', 'ip_range' => '10.0.0.0/8'])
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)->post('/networks', ['name' => str_repeat('a', 101), 'ip_range' => '10.0.0.0/8'])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, ApprovedNetwork::count());
    }

    public function test_update_changes_the_name_and_range_and_audits_only_what_changed(): void
    {
        $network = $this->network(['name' => 'Old name', 'ip_range' => '10.0.0.0/8']);

        $this->actingAs($this->admin())->put("/networks/{$network->id}", [
            'name' => 'New name',
            'ip_range' => '10.0.0.0/8',
        ])->assertSessionHasNoErrors();

        $network->refresh();

        $this->assertSame('New name', $network->name);

        $row = AuditLog::where('action', 'network.updated')->firstOrFail();

        $this->assertSame(['name' => 'Old name'], $row->old_values);
        $this->assertSame(['name' => 'New name'], $row->new_values);
    }

    public function test_saving_without_changes_writes_no_audit_row(): void
    {
        $network = $this->network(['name' => 'Same', 'ip_range' => '10.0.0.0/8']);

        $this->actingAs($this->admin())->put("/networks/{$network->id}", [
            'name' => 'Same',
            'ip_range' => '10.0.0.0/8',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('audit_logs', ['action' => 'network.updated']);
    }

    public function test_a_network_can_be_deactivated_and_activated_again_with_audit_rows(): void
    {
        $network = $this->network();
        $admin = $this->admin();

        $this->actingAs($admin)->patch("/networks/{$network->id}/deactivate")->assertSessionHasNoErrors();

        $this->assertFalse($network->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'network.deactivated', 'entity_id' => $network->id]);
        $this->assertFalse(ApprovedNetwork::allows('192.168.1.5'));

        $this->actingAs($admin)->patch("/networks/{$network->id}/activate")->assertSessionHasNoErrors();

        $this->assertTrue($network->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'network.activated', 'entity_id' => $network->id]);
        $this->assertTrue(ApprovedNetwork::allows('192.168.1.5'));
    }

    public function test_no_route_can_delete_a_network(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'networks')) {
                $this->assertNotContains('DELETE', $route->methods());
            }
        }
    }
}