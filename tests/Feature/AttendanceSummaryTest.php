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

class AttendanceSummaryTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        // Friday 30 October 2026, 15:00 in Addis Ababa. Yesterday is Thursday the 29th.
        $this->travelTo(Carbon::parse('2026-10-30 12:00:00', 'UTC'));
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $roleName)->firstOrFail()->id);

        return $user;
    }

    private function hr(): User
    {
        return $this->userWithRole('HR Manager');
    }

    private function position(string $department = 'Operations', string $title = 'Clerk'): Position
    {
        $dept = Department::firstOrCreate(['name' => $department]);

        return Position::firstOrCreate(['department_id' => $dept->id, 'title' => $title]);
    }

    private function employee(string $status = 'active', string $first = 'Ana', string $hired = '2026-01-05', ?Position $position = null): Employee
    {
        $this->counter++;

        return Employee::create([
            'position_id' => ($position ?? $this->position())->id,
            'employee_number' => 'T-'.$this->counter,
            'first_name' => $first,
            'last_name' => 'Tesfaye',
            'hire_date' => $hired,
            'employment_status' => $status,
        ]);
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
        $this->get('/attendance/summary')->assertRedirect('/login');

        foreach (['Employee', 'Attendance Scanner', 'Sales Clerk'] as $role) {
            $this->actingAs($this->userWithRole($role))->get('/attendance/summary')->assertForbidden();
        }

        $this->actingAs($this->hr())->get('/attendance/summary')->assertOk();
        $this->actingAs($this->userWithRole('Manager'))->get('/attendance/summary')->assertOk();
    }

    public function test_the_numbers_of_one_month_are_worked_out_per_employee(): void
    {
        $ana = $this->employee('active', 'Ana');

        $this->record($ana, '2026-10-01', '2026-10-01 06:00:00', '2026-10-01 14:05:00');
        $this->record($ana, '2026-10-02', '2026-10-02 06:20:00', '2026-10-02 14:00:00', 'late');
        $this->record($ana, '2026-10-05', '2026-10-05 06:00:00');
        // Today: still at work, so this is not a missing check-out.
        $this->record($ana, '2026-10-30', '2026-10-30 06:00:00');

        $this->actingAs($this->hr())->get('/attendance/summary')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Attendance/Summary')
                ->where('month', '2026-10')
                ->where('countedThrough', '2026-10-29')
                ->where('rows.total', 1)
                ->where('rows.data.0.name', 'Ana Tesfaye')
                ->where('rows.data.0.days', 4)
                ->where('rows.data.0.late', 1)
                ->where('rows.data.0.minutes', 945)
                ->where('rows.data.0.worked', '15h 45m')
                ->where('rows.data.0.missing', 1)
                // 21 working days from the 1st to the 29th, minus the 3 weekdays with a record.
                ->where('rows.data.0.absent', 18));
    }

    public function test_a_weekend_record_counts_as_worked_but_not_against_absence(): void
    {
        $ana = $this->employee();

        $this->record($ana, '2026-10-01', '2026-10-01 06:00:00', '2026-10-01 14:00:00');
        // Saturday 3 October is not a working day.
        $this->record($ana, '2026-10-03', '2026-10-03 06:00:00', '2026-10-03 10:00:00');

        $this->actingAs($this->hr())->get('/attendance/summary')
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.data.0.days', 2)
                ->where('rows.data.0.absent', 20));
    }

    public function test_absence_starts_at_the_hire_date(): void
    {
        // Monday 26 October: only the 26th, 27th, 28th and 29th are counted.
        $this->employee('active', 'Newcomer', '2026-10-26');

        $this->actingAs($this->hr())->get('/attendance/summary')
            ->assertInertia(fn (Assert $page) => $page->where('rows.data.0.absent', 4));
    }

    public function test_people_hired_after_the_month_are_not_listed(): void
    {
        $this->employee('active', 'Later', '2026-11-15');

        $this->actingAs($this->hr())->get('/attendance/summary')
            ->assertInertia(fn (Assert $page) => $page->where('rows.total', 0));
    }

    public function test_absence_is_only_worked_out_for_active_employees(): void
    {
        $leave = $this->employee('on_leave', 'Off');
        $left = $this->employee('terminated', 'Left');
        $this->employee('terminated', 'Gone');

        $this->record($leave, '2026-10-01', '2026-10-01 06:00:00', '2026-10-01 14:00:00');
        $this->record($left, '2026-10-02', '2026-10-02 06:00:00', '2026-10-02 14:00:00');

        $this->actingAs($this->hr())->get('/attendance/summary')
            ->assertInertia(fn (Assert $page) => $page
                // "Gone" has no record and is not active, so only two people are listed.
                ->where('rows.total', 2)
                ->where('rows.data.0.name', 'Off Tesfaye')
                ->where('rows.data.0.days', 1)
                ->where('rows.data.0.absent', null)
                ->where('rows.data.1.name', 'Left Tesfaye')
                ->where('rows.data.1.absent', null));
    }

    public function test_a_past_month_counts_every_working_day(): void
    {
        $this->employee();

        $this->actingAs($this->hr())->get('/attendance/summary?month=2026-09')
            ->assertInertia(fn (Assert $page) => $page
                ->where('month', '2026-09')
                ->where('countedThrough', '2026-09-30')
                ->where('rows.data.0.days', 0)
                // September 2026 has 22 weekdays.
                ->where('rows.data.0.absent', 22));
    }

    public function test_a_future_month_is_refused(): void
    {
        $this->actingAs($this->hr())->get('/attendance/summary?month=2026-11')->assertSessionHasErrors('month');
    }

    public function test_the_default_month_is_the_business_month(): void
    {
        // 22:30 UTC on the 31st is already 01:30 on 1 November in Addis Ababa.
        $this->travelTo(Carbon::parse('2026-10-31 22:30:00', 'UTC'));

        $this->actingAs($this->hr())->get('/attendance/summary')
            ->assertInertia(fn (Assert $page) => $page
                ->where('month', '2026-11')
                ->where('currentMonth', '2026-11'));
    }

    public function test_working_days_come_from_the_setting_and_fall_back_when_unreadable(): void
    {
        $this->employee();
        $hr = $this->hr();

        config(['attendance.working_days' => '1,2,3,4,5,6']);

        // Four Saturdays (3, 10, 17, 24) join the 21 weekdays.
        $this->actingAs($hr)->get('/attendance/summary')
            ->assertInertia(fn (Assert $page) => $page->where('rows.data.0.absent', 25));

        config(['attendance.working_days' => 'x']);

        $this->actingAs($hr)->get('/attendance/summary')
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.data.0.absent', 21)
                ->where('workingDayNames', ['Mon', 'Tue', 'Wed', 'Thu', 'Fri']));
    }

    public function test_the_list_can_be_filtered_by_department(): void
    {
        $finance = $this->position('Finance', 'Accountant');

        $this->employee('active', 'Fina', '2026-01-05', $finance);
        $this->employee('active', 'Opal');

        $this->actingAs($this->hr())->get('/attendance/summary?department='.$finance->department_id)
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.total', 1)
                ->where('rows.data.0.name', 'Fina Tesfaye'));
    }

    public function test_a_badly_written_month_is_refused(): void
    {
        $this->actingAs($this->hr())->get('/attendance/summary?month=10-2026')->assertSessionHasErrors('month');
    }

    public function test_on_the_first_day_of_the_month_nothing_is_counted_yet(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01 06:00:00', 'UTC'));

        $this->employee();

        $this->actingAs($this->hr())->get('/attendance/summary')
            ->assertInertia(fn (Assert $page) => $page
                ->where('countedThrough', null)
                ->where('rows.data.0.absent', 0));
    }
}