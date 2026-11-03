<?php

namespace Tests\Feature;

use App\Models\ApprovedNetwork;
use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
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

class AttendanceScanTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        // 06:00 UTC is 09:00 in Addis Ababa. The test client's address is 127.0.0.1.
        $this->travelTo(Carbon::parse('2026-10-30 06:00:00', 'UTC'));

        ApprovedNetwork::create(['name' => 'Office', 'ip_range' => '127.0.0.1/32', 'is_active' => true]);
    }

    private function scanner(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'Attendance Scanner')->firstOrFail()->id);

        return $user;
    }

    private function employee(string $status = 'active', string $first = 'Ana'): Employee
    {
        $this->counter++;

        $department = Department::firstOrCreate(['name' => 'Operations']);
        $position = Position::firstOrCreate(['department_id' => $department->id, 'title' => 'Clerk']);

        return Employee::create([
            'position_id' => $position->id,
            'employee_number' => 'T-'.$this->counter,
            'first_name' => $first,
            'last_name' => 'Tesfaye',
            'hire_date' => '2026-01-05',
            'employment_status' => $status,
        ]);
    }

    private function token(Employee $employee): string
    {
        return app(QrTokenService::class)->issue($employee)['token'];
    }

    private function scan(User $scanner, string $token, string $ip = '127.0.0.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->actingAs($scanner)
            ->withHeader('User-Agent', 'TestScanner/1.0')
            ->postJson('/attendance/scan', ['token' => $token]);
    }

    private function at(string $utc): void
    {
        $this->travelTo(Carbon::parse($utc, 'UTC'));
    }

    private function reasons(): array
    {
        return AuditLog::where('action', 'attendance.scan_rejected')
            ->orderBy('id')->get()->pluck('new_values.reason')->all();
    }

    public function test_guests_and_users_without_the_permission_are_refused(): void
    {
        $token = $this->token($this->employee());

        $this->postJson('/attendance/scan', ['token' => $token])->assertUnauthorized();

        $employeeUser = User::factory()->create();
        $employeeUser->roles()->attach(Role::where('name', 'Employee')->firstOrFail()->id);

        $this->actingAs($employeeUser)->postJson('/attendance/scan', ['token' => $token])->assertForbidden();

        $this->assertSame(0, AttendanceRecord::count());
    }

    public function test_a_code_that_is_not_ours_is_refused_without_an_audit_row(): void
    {
        $this->scan($this->scanner(), 'https://example.test/some-other-qr')
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'code' => 'not_attendance_code']);

        $this->assertDatabaseMissing('audit_logs', ['action' => 'attendance.scan_rejected']);
    }

    public function test_the_first_scan_of_the_day_checks_in(): void
    {
        $scanner = $this->scanner();
        $employee = $this->employee();
        $token = $this->token($employee);

        $this->scan($scanner, $token)
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'code' => 'check_in',
                'employee' => 'Ana Tesfaye',
                'number' => 'T-1',
                'time' => '09:00:00',
            ]);

        $record = AttendanceRecord::firstOrFail();

        $this->assertSame($employee->id, $record->employee_id);
        $this->assertSame('2026-10-30', $record->attendance_date->format('Y-m-d'));
        $this->assertSame('2026-10-30 06:00:00', $record->check_in_at->format('Y-m-d H:i:s'));
        $this->assertNull($record->check_out_at);
        $this->assertSame('present', $record->status);

        $event = AttendanceEvent::firstOrFail();

        $this->assertSame('check_in', $event->event_type);
        $this->assertSame('qr', $event->method);
        $this->assertSame('127.0.0.1', $event->ip_address);
        $this->assertSame('TestScanner/1.0', $event->device_info);

        // The code is used up.
        $this->assertNotNull(QrCode::firstOrFail()->revoked_at);
    }

    public function test_the_attendance_date_follows_the_business_time_zone(): void
    {
        // 22:30 UTC on the 30th is already 01:30 on the 31st in Addis Ababa.
        $this->at('2026-10-30 22:30:00');

        $employee = $this->employee();

        $this->scan($this->scanner(), $this->token($employee))->assertOk();

        $record = AttendanceRecord::firstOrFail();

        $this->assertSame('2026-10-31', $record->attendance_date->format('Y-m-d'));
        $this->assertSame('2026-10-30 22:30:00', $record->check_in_at->format('Y-m-d H:i:s'));
    }

    public function test_the_second_scan_checks_out(): void
    {
        $scanner = $this->scanner();
        $employee = $this->employee();

        $this->scan($scanner, $this->token($employee))->assertOk();

        $this->at('2026-10-30 06:02:00');

        $this->scan($scanner, $this->token($employee))
            ->assertOk()
            ->assertJson(['ok' => true, 'code' => 'check_out', 'time' => '09:02:00']);

        $record = AttendanceRecord::firstOrFail();

        $this->assertSame('2026-10-30 06:02:00', $record->check_out_at->format('Y-m-d H:i:s'));
        $this->assertSame(1, AttendanceRecord::count());
        $this->assertSame(['check_in', 'check_out'], AttendanceEvent::orderBy('id')->pluck('event_type')->all());
    }

    public function test_a_scan_within_the_minimum_gap_is_refused_and_keeps_the_code(): void
    {
        $scanner = $this->scanner();
        $employee = $this->employee();

        $this->scan($scanner, $this->token($employee))->assertOk();

        $this->at('2026-10-30 06:00:30');

        $second = $this->token($employee);

        $this->scan($scanner, $second)->assertStatus(422)->assertJson(['ok' => false, 'code' => 'too_soon']);

        $this->assertNull(AttendanceRecord::firstOrFail()->check_out_at);
        $this->assertSame(1, AttendanceEvent::count());
        $this->assertNull(QrCode::where('token_hash', QrTokenService::hash($second))->firstOrFail()->revoked_at);
        $this->assertSame(['too_soon'], $this->reasons());
    }

    public function test_a_third_scan_is_refused(): void
    {
        $scanner = $this->scanner();
        $employee = $this->employee();

        $this->scan($scanner, $this->token($employee))->assertOk();

        $this->at('2026-10-30 06:02:00');
        $this->scan($scanner, $this->token($employee))->assertOk();

        $this->at('2026-10-30 06:05:00');
        $this->scan($scanner, $this->token($employee))
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'code' => 'already_completed']);

        $this->assertSame(2, AttendanceEvent::count());
        $this->assertSame(['already_completed'], $this->reasons());
    }

    public function test_an_unknown_code_is_refused_and_audited(): void
    {
        $scanner = $this->scanner();

        $this->scan($scanner, str_repeat('a', 64))
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'code' => 'unknown_token']);

        $this->assertSame(0, AttendanceRecord::count());

        $row = AuditLog::where('action', 'attendance.scan_rejected')->firstOrFail();

        $this->assertSame($scanner->id, $row->user_id);
        $this->assertSame('127.0.0.1', $row->ip_address);
        $this->assertNull($row->entity_id);
        $this->assertSame(['reason' => 'unknown_token'], $row->new_values);
    }

    public function test_an_expired_code_is_refused(): void
    {
        $scanner = $this->scanner();
        $employee = $this->employee();
        $token = $this->token($employee);

        $this->at('2026-10-30 06:01:01');

        $this->scan($scanner, $token)->assertStatus(422)->assertJson(['ok' => false, 'code' => 'expired']);

        $this->assertSame(0, AttendanceRecord::count());
        $this->assertSame(['expired'], $this->reasons());
        $this->assertEquals($employee->id, AuditLog::firstOrFail()->entity_id);
    }

    public function test_a_used_code_cannot_be_used_again(): void
    {
        $scanner = $this->scanner();
        $token = $this->token($this->employee());

        $this->scan($scanner, $token)->assertOk();
        $this->scan($scanner, $token)->assertStatus(422)->assertJson(['ok' => false, 'code' => 'revoked']);

        $this->assertSame(1, AttendanceEvent::count());
    }

    public function test_a_newer_code_cancels_the_older_one(): void
    {
        $scanner = $this->scanner();
        $employee = $this->employee();

        $older = $this->token($employee);
        $this->token($employee);

        $this->scan($scanner, $older)->assertStatus(422)->assertJson(['ok' => false, 'code' => 'revoked']);

        $this->assertSame(0, AttendanceRecord::count());
    }

    public function test_employees_who_are_not_active_are_refused(): void
    {
        $scanner = $this->scanner();

        foreach (['on_leave', 'suspended', 'terminated'] as $status) {
            $employee = $this->employee($status);

            $this->scan($scanner, $this->token($employee))
                ->assertStatus(422)
                ->assertJson(['ok' => false, 'code' => 'employee_inactive']);
        }

        $this->assertSame(0, AttendanceRecord::count());
        $this->assertSame(['employee_inactive', 'employee_inactive', 'employee_inactive'], $this->reasons());
    }

    public function test_a_scanner_outside_the_approved_networks_is_refused_and_the_code_is_kept(): void
    {
        $scanner = $this->scanner();
        $token = $this->token($this->employee());

        $this->scan($scanner, $token, '10.9.9.9')
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'code' => 'outside_network']);

        $this->assertSame(0, AttendanceRecord::count());
        $this->assertNull(QrCode::firstOrFail()->revoked_at);
        $this->assertSame('10.9.9.9', AuditLog::firstOrFail()->ip_address);

        // The same code works from the approved network.
        $this->scan($scanner, $token)->assertOk()->assertJson(['code' => 'check_in']);
    }

    public function test_with_no_active_network_every_scan_is_refused(): void
    {
        ApprovedNetwork::query()->update(['is_active' => false]);

        $this->scan($this->scanner(), $this->token($this->employee()))
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'code' => 'outside_network']);

        $this->assertSame(0, AttendanceRecord::count());
    }

    public function test_the_checks_run_in_the_documented_order(): void
    {
        $scanner = $this->scanner();

        // Expired, suspended and outside the network: the first failing check wins.
        $expired = $this->token($this->employee('suspended'));
        $this->at('2026-10-30 06:05:00');

        $this->scan($scanner, $expired, '10.9.9.9')->assertJson(['code' => 'expired']);

        // Suspended and outside the network: the employee check comes before the network check.
        $suspended = $this->token($this->employee('suspended', 'Bekele'));

        $this->scan($scanner, $suspended, '10.9.9.9')->assertJson(['code' => 'employee_inactive']);

        $this->assertSame(['expired', 'employee_inactive'], $this->reasons());
    }

    public function test_the_audit_log_never_holds_the_code(): void
    {
        $token = $this->token($this->employee());

        $this->at('2026-10-30 06:05:00');
        $this->scan($this->scanner(), $token);

        $this->assertStringNotContainsString($token, json_encode(AuditLog::all()->toArray()));
        $this->assertStringNotContainsString(QrTokenService::hash($token), json_encode(AuditLog::all()->toArray()));
    }

    public function test_a_new_day_starts_a_new_record(): void
    {
        $scanner = $this->scanner();
        $employee = $this->employee();

        $this->scan($scanner, $this->token($employee))->assertOk();

        $this->at('2026-10-30 06:02:00');
        $this->scan($scanner, $this->token($employee))->assertOk();

        $this->at('2026-10-31 06:00:00');
        $this->scan($scanner, $this->token($employee))->assertOk()->assertJson(['code' => 'check_in']);

        $this->assertSame(
            ['2026-10-30', '2026-10-31'],
            AttendanceRecord::orderBy('id')->get()->map(fn ($r) => $r->attendance_date->format('Y-m-d'))->all(),
        );
    }

    public function test_employees_are_independent(): void
    {
        $scanner = $this->scanner();

        $this->scan($scanner, $this->token($this->employee('active', 'Ana')))->assertOk();
        $this->scan($scanner, $this->token($this->employee('active', 'Bekele')))->assertOk();

        $this->assertSame(2, AttendanceRecord::count());
        $this->assertSame(2, AttendanceEvent::where('event_type', 'check_in')->count());
    }

    public function test_the_scanner_page_is_only_for_scanners(): void
    {
        $this->get('/scanner')->assertRedirect('/login');

        $this->actingAs($this->scanner())->get('/scanner')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Attendance/Scanner'));

        $employeeUser = User::factory()->create();
        $employeeUser->roles()->attach(Role::where('name', 'Employee')->firstOrFail()->id);

        $this->actingAs($employeeUser)->get('/scanner')->assertForbidden();
    }
}