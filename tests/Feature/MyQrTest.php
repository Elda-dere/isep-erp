<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\QrCode;
use App\Models\Role;
use App\Models\User;
use App\Services\QrTokenService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MyQrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    /** @return array{0: User, 1: Employee} */
    private function staff(string $status = 'active', string $role = 'Employee'): array
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $role)->firstOrFail()->id);

        $department = Department::firstOrCreate(['name' => 'Operations']);
        $position = Position::firstOrCreate(['department_id' => $department->id, 'title' => 'Clerk']);

        $employee = Employee::create([
            'user_id' => $user->id,
            'position_id' => $position->id,
            'employee_number' => 'T-'.$user->id,
            'first_name' => 'Ana',
            'last_name' => 'Tesfaye',
            'hire_date' => '2026-01-05',
            'employment_status' => $status,
        ]);

        return [$user, $employee];
    }

    public function test_guests_and_users_without_the_permission_are_refused(): void
    {
        $this->get('/my-qr')->assertRedirect('/login');

        [$scanner] = $this->staff('active', 'Attendance Scanner');

        $this->actingAs($scanner)->get('/my-qr')->assertForbidden();
    }

    public function test_an_active_employee_gets_a_code_and_exactly_one_token_is_made(): void
    {
        [$user, $employee] = $this->staff();

        $this->actingAs($user)->get('/my-qr')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Attendance/MyQr')
                ->where('state', 'ready')
                ->where('employeeName', 'Ana Tesfaye')
                ->where('qr.expires_in', 60)
                ->where('qr.token', fn ($token) => preg_match('/^[0-9a-f]{64}$/', $token) === 1));

        $this->assertSame(1, QrCode::where('employee_id', $employee->id)->count());
    }

    public function test_only_the_hash_is_stored_never_the_token(): void
    {
        [, $employee] = $this->staff();

        $issued = app(QrTokenService::class)->issue($employee);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $issued['token']);
        $this->assertDatabaseHas('qr_codes', ['token_hash' => hash('sha256', $issued['token'])]);
        $this->assertDatabaseMissing('qr_codes', ['token_hash' => $issued['token']]);
    }

    public function test_a_new_token_cancels_the_earlier_one_of_the_same_employee_only(): void
    {
        [, $ana] = $this->staff();
        [, $bekele] = $this->staff();

        $service = app(QrTokenService::class);

        $first = $service->issue($ana);
        $other = $service->issue($bekele);
        $second = $service->issue($ana);

        $row = fn (array $issued) => QrCode::where('token_hash', QrTokenService::hash($issued['token']))->firstOrFail();

        $this->assertNotNull($row($first)->revoked_at);
        $this->assertNull($row($second)->revoked_at);
        $this->assertNull($row($other)->revoked_at);
    }

    public function test_the_expiry_follows_the_setting_and_is_stored_in_utc(): void
    {
        [, $employee] = $this->staff();

        $this->travelTo(Carbon::parse('2026-10-30 10:00:00', 'UTC'));
        config(['attendance.qr_lifetime_seconds' => 30]);

        $issued = app(QrTokenService::class)->issue($employee);

        $row = QrCode::firstOrFail();

        $this->assertSame(30, $issued['expires_in']);
        $this->assertSame('2026-10-30 10:00:00', $row->created_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-30 10:00:30', $row->expires_at->format('Y-m-d H:i:s'));
    }

    public function test_an_expired_token_is_left_alone_by_a_new_one(): void
    {
        [, $employee] = $this->staff();
        $service = app(QrTokenService::class);

        $this->travelTo(Carbon::parse('2026-10-30 10:00:00', 'UTC'));
        $old = $service->issue($employee);

        $this->travelTo(Carbon::parse('2026-10-30 10:05:00', 'UTC'));
        $service->issue($employee);

        $this->assertNull(QrCode::where('token_hash', QrTokenService::hash($old['token']))->firstOrFail()->revoked_at);
    }

    public function test_the_lifetime_is_kept_between_10_and_300_seconds(): void
    {
        $service = app(QrTokenService::class);

        config(['attendance.qr_lifetime_seconds' => 1]);
        $this->assertSame(10, $service->lifetime());

        config(['attendance.qr_lifetime_seconds' => 99999]);
        $this->assertSame(300, $service->lifetime());
    }

    public function test_a_login_without_an_employee_record_gets_a_message_and_no_token(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'Employee')->firstOrFail()->id);

        $this->actingAs($user)->get('/my-qr')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('state', 'no_employee')
                ->missing('qr'));

        $this->assertSame(0, QrCode::count());
    }

    public function test_employees_who_are_not_active_get_a_message_and_no_token(): void
    {
        foreach (['on_leave', 'suspended', 'terminated'] as $status) {
            [$user] = $this->staff($status);

            $this->actingAs($user)->get('/my-qr')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('state', 'blocked')
                    ->missing('qr'));
        }

        $this->assertSame(0, QrCode::count());
    }
}