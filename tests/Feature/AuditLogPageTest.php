<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuditLogPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function viewer(): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Auditor']);
        $role->permissions()->attach(Permission::create(['name' => 'audit.view'])->id);
        $user->roles()->attach($role);

        return $user;
    }

    private function row(string $action, ?int $userId = null, string $at = '2026-10-20 10:00:00', ?string $ip = '10.0.0.1'): AuditLog
    {
        return AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => 'product',
            'entity_id' => 1,
            'ip_address' => $ip,
            'created_at' => $at,
        ]);
    }

    public function test_guest_is_sent_to_the_login_page(): void
    {
        $this->get('/audit-logs')->assertRedirect('/login');
    }

    public function test_user_without_the_permission_gets_403(): void
    {
        $this->actingAs(User::factory()->create())->get('/audit-logs')->assertForbidden();
    }

    public function test_viewer_sees_the_entries(): void
    {
        $this->row('auth.login');
        $this->row('product.created');

        $this->actingAs($this->viewer())->get('/audit-logs')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Audit/Index')
                ->where('logs.total', 2)
                ->has('logs.data', 2)
            );
    }

    public function test_it_filters_by_action_prefix(): void
    {
        $this->row('auth.login');
        $this->row('auth.login_failed');
        $this->row('product.created');

        $this->actingAs($this->viewer())->get('/audit-logs?action=auth.')
            ->assertInertia(fn (Assert $page) => $page->where('logs.total', 2));
    }

    public function test_it_filters_by_user(): void
    {
        $ana = User::factory()->create();
        $this->row('auth.login', $ana->id);
        $this->row('auth.login', null);

        $this->actingAs($this->viewer())->get('/audit-logs?user='.$ana->id)
            ->assertInertia(fn (Assert $page) => $page->where('logs.total', 1));
    }

    public function test_it_filters_by_ip_address(): void
    {
        $this->row('auth.login', null, '2026-10-20 10:00:00', '10.0.0.1');
        $this->row('auth.login', null, '2026-10-20 10:00:00', '10.0.0.2');

        $this->actingAs($this->viewer())->get('/audit-logs?ip=10.0.0.2')
            ->assertInertia(fn (Assert $page) => $page->where('logs.total', 1));
    }

    public function test_it_filters_by_date_in_business_time(): void
    {
        $this->row('older', null, '2026-10-20 10:00:00');
        $this->row('newer', null, '2026-10-22 10:00:00');

        $viewer = $this->viewer();

        $this->actingAs($viewer)->get('/audit-logs?from=2026-10-21')
            ->assertInertia(fn (Assert $page) => $page
                ->where('logs.total', 1)
                ->where('logs.data.0.action', 'newer'));

        $this->actingAs($viewer)->get('/audit-logs?to=2026-10-21')
            ->assertInertia(fn (Assert $page) => $page
                ->where('logs.total', 1)
                ->where('logs.data.0.action', 'older'));
    }

    public function test_a_to_date_before_the_from_date_is_rejected(): void
    {
        $this->actingAs($this->viewer())
            ->get('/audit-logs?from=2026-10-22&to=2026-10-20')
            ->assertSessionHasErrors('to');
    }

    public function test_times_are_stored_in_utc_and_shown_in_addis_ababa_time(): void
    {
        $this->assertSame('UTC', config('app.timezone'));

        $this->row('auth.login', null, '2026-10-24 21:30:00');

        $this->actingAs($this->viewer())->get('/audit-logs')
            ->assertInertia(fn (Assert $page) => $page
                ->where('logs.data.0.created_at', '2026-10-25 00:30:00'));
    }

    public function test_pages_hold_25_rows(): void
    {
        foreach (range(1, 30) as $i) {
            $this->row('bulk.'.$i);
        }

        $this->actingAs($this->viewer())->get('/audit-logs')
            ->assertInertia(fn (Assert $page) => $page
                ->where('logs.total', 30)
                ->has('logs.data', 25));
    }

    public function test_no_route_can_change_audit_logs(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (str_contains($route->uri(), 'audit')) {
                $this->assertEqualsCanonicalizing(['GET', 'HEAD'], $route->methods());
            }
        }
    }
}