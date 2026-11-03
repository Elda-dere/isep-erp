<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MyAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        // 06:00 UTC is 09:00 in Addis Ababa.
        $this->travelTo(Carbon::parse('2026-10-30 06:00:00', 'UTC'));
    }

    private function employeeWithLogin(string $role = 'Employee'): array
    {
        $this->counter++;

        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $role)->firstOrFail()->id);

        $department = Department::firstOrCreate(['name' => 'Operations']);
        $position = Position::firstOrCreate(['department_id' => $department->id, 'title' => 'Clerk']);

        $employee = Employee::create([
            'user_id' => $user->id,
            'position_id' => $position->id,
            'employee_number' => 'T-'.$this->counter,
            'first_name' => 'Ana',
            'last_name' => 'Tesfaye',
            'hire_date' => '2026-01-05',
            'employment_status' => 'active',
        ]);

        return [$user, $employee];
    }

    private function record(Employee $employee, string $date, string $inUtc, ?string $outUtc = null, string $status = 'present'): AttendanceRecord
    {
        return AttendanceRecord::create([
            'employee_id' => $employee->id,
            'attendance_date' => $date,
            'check_in_at' => Carbon::parse($inUtc, 'UTC'),
            'check_out_at' => $outUtc === null ? null : Carbon::parse($outUtc, 'UTC'),
            'status' => $status,
            'created_at' => now(),
        ]);
    }

    public function test_guests_and_users_without_the_permission_are_refused(): void
    {
        $this->get('/my-attendance')->assertRedirect('/login');

        [$scanner] = $this->employeeWithLogin('Attendance Scanner');

        $this->actingAs($scanner)->get('/my-attendance')->assertForbidden();
    }

    public function test_a_login_without_an_employee_record_gets_a_message(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'Employee')->firstOrFail()->id);

        $this->actingAs($user)->get('/my-attendance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Attendance/MyAttendance')
                ->where('state', 'no_employee')
                ->missing('records'));
    }

    public function test_today_says_not_checked_in_when_there_is_no_record(): void
    {
        [$user] = $this->employeeWithLogin();

        $this->actingAs($user)->get('/my-attendance')
            ->assertInertia(fn (Assert $page) => $page
                ->where('state', 'ready')
                ->where('today.date', '2026-10-30')
                ->where('today.state', 'not_in')
                ->where('today.late', false)
                ->where('today.check_in', null)
                ->where('records.total', 0));
    }

    public function test_today_shows_the_check_in_in_business_time_and_the_late_flag(): void
    {
        [$user, $employee] = $this->employeeWithLogin();

        $this->record($employee, '2026-10-30', '2026-10-30 06:00:00', null, 'late');

        $this->actingAs($user)->get('/my-attendance')
            ->assertInertia(fn (Assert $page) => $page
                ->where('today.state', 'in')
                ->where('today.late', true)
                ->where('today.check_in', '09:00')
                ->where('today.check_out', null)
                ->where('today.worked', null));
    }

    public function test_today_shows_the_hours_worked_after_check_out(): void
    {
        [$user, $employee] = $this->employeeWithLogin();

        $this->record($employee, '2026-10-30', '2026-10-30 06:00:00', '2026-10-30 14:05:00');

        $this->actingAs($user)->get('/my-attendance')
            ->assertInertia(fn (Assert $page) => $page
                ->where('today.state', 'out')
                ->where('today.check_out', '17:05')
                ->where('today.worked', '8h 05m'));
    }

    public function test_the_history_holds_only_my_days_newest_first(): void
    {
        [$user, $employee] = $this->employeeWithLogin();
        [, $colleague] = $this->employeeWithLogin();

        $this->record($employee, '2026-10-28', '2026-10-28 06:00:00', '2026-10-28 14:00:00');
        $this->record($employee, '2026-10-29', '2026-10-29 06:20:00', null, 'late');
        $this->record($colleague, '2026-10-29', '2026-10-29 06:00:00');

        $this->actingAs($user)->get('/my-attendance')
            ->assertInertia(fn (Assert $page) => $page
                ->where('records.total', 2)
                ->where('records.data.0.date', '2026-10-29')
                ->where('records.data.0.status', 'late')
                ->where('records.data.1.date', '2026-10-28')
                ->where('records.data.1.worked', '8h 00m'));
    }

    public function test_the_history_holds_25_days_per_page(): void
    {
        [$user, $employee] = $this->employeeWithLogin();

        foreach (range(1, 30) as $day) {
            $date = Carbon::parse('2026-09-01', 'UTC')->addDays($day)->toDateString();

            $this->record($employee, $date, $date.' 06:00:00');
        }

        $this->actingAs($user)->get('/my-attendance')
            ->assertInertia(fn (Assert $page) => $page
                ->where('records.total', 30)
                ->has('records.data', 25));
    }
}