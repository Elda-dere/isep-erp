<?php

namespace Tests\Feature;

use App\Models\ApprovedNetwork;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\QrTokenService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceLateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    private function statusOf(string $utc): string
    {
        return app(AttendanceService::class)->statusFor(Carbon::parse($utc, 'UTC'));
    }

    private function scanAt(string $utc): AttendanceRecord
    {
        $this->travelTo(Carbon::parse($utc, 'UTC'));

        ApprovedNetwork::create(['name' => 'Office', 'ip_range' => '127.0.0.1/32', 'is_active' => true]);

        $scanner = User::factory()->create();
        $scanner->roles()->attach(Role::where('name', 'Attendance Scanner')->firstOrFail()->id);

        $department = Department::create(['name' => 'Operations']);
        $position = Position::create(['department_id' => $department->id, 'title' => 'Clerk']);

        $employee = Employee::create([
            'position_id' => $position->id,
            'employee_number' => 'T-1',
            'first_name' => 'Ana',
            'last_name' => 'Tesfaye',
            'hire_date' => '2026-01-05',
            'employment_status' => 'active',
        ]);

        $token = app(QrTokenService::class)->issue($employee)['token'];

        $this->actingAs($scanner)->postJson('/attendance/scan', ['token' => $token])->assertOk();

        return AttendanceRecord::firstOrFail();
    }

    public function test_lateness_is_off_until_a_start_time_is_configured(): void
    {
        config(['attendance.start_time' => null, 'attendance.grace_minutes' => 0]);

        $this->assertSame('present', $this->statusOf('2026-10-30 20:00:00'));

        config(['attendance.start_time' => '']);

        $this->assertSame('present', $this->statusOf('2026-10-30 20:00:00'));
    }

    public function test_a_person_is_on_time_until_the_grace_period_has_passed(): void
    {
        // The start time 09:00 is business time: 06:00 UTC.
        config(['attendance.start_time' => '09:00', 'attendance.grace_minutes' => 10]);

        $this->assertSame('present', $this->statusOf('2026-10-30 05:00:00'));
        $this->assertSame('present', $this->statusOf('2026-10-30 06:00:00'));
        $this->assertSame('present', $this->statusOf('2026-10-30 06:10:00'));
        $this->assertSame('late', $this->statusOf('2026-10-30 06:10:01'));
        $this->assertSame('late', $this->statusOf('2026-10-30 09:00:00'));
    }

    public function test_without_a_grace_period_the_start_time_itself_is_the_deadline(): void
    {
        config(['attendance.start_time' => '09:00', 'attendance.grace_minutes' => 0]);

        $this->assertSame('present', $this->statusOf('2026-10-30 06:00:00'));
        $this->assertSame('late', $this->statusOf('2026-10-30 06:00:01'));
    }

    public function test_the_start_time_is_read_in_business_time_not_utc(): void
    {
        config(['attendance.start_time' => '09:00', 'attendance.grace_minutes' => 0]);

        // 08:59:59 in Addis Ababa is on time. 12:00 in Addis Ababa (09:00 UTC) is late.
        $this->assertSame('present', $this->statusOf('2026-10-30 05:59:59'));
        $this->assertSame('late', $this->statusOf('2026-10-30 09:00:00'));
    }

    public function test_a_badly_written_start_time_switches_lateness_off(): void
    {
        foreach (['9am', '25:00', '09:60', 'late', '9'] as $value) {
            config(['attendance.start_time' => $value, 'attendance.grace_minutes' => 0]);

            $this->assertSame('present', $this->statusOf('2026-10-30 20:00:00'), "Start time '{$value}' should be ignored");
        }
    }

    public function test_a_scan_after_the_deadline_creates_a_late_record(): void
    {
        config(['attendance.start_time' => '09:00', 'attendance.grace_minutes' => 10]);

        // 06:30 UTC is 09:30 in Addis Ababa.
        $this->assertSame('late', $this->scanAt('2026-10-30 06:30:00')->status);
    }

    public function test_a_scan_before_the_deadline_creates_a_present_record(): void
    {
        config(['attendance.start_time' => '10:00', 'attendance.grace_minutes' => 0]);

        $this->assertSame('present', $this->scanAt('2026-10-30 06:30:00')->status);
    }
}
