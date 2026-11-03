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

class AttendanceBoardTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        // 12:00 UTC is 15:00 in Addis Ababa, on the 30th.
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

    private function record(Employee $employee, string $inUtc, ?string $outUtc = null, string $status = 'present', string $date = '2026-10-30'): AttendanceRecord
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
        $this->get('/attendance/board')->assertRedirect('/login');

        foreach (['Employee', 'Attendance Scanner', 'Sales Clerk'] as $role) {
            $this->actingAs($this->userWithRole($role))->get('/attendance/board')->assertForbidden();
        }

        $this->actingAs($this->hr())->get('/attendance/board')->assertOk();
        $this->actingAs($this->userWithRole('Manager'))->get('/attendance/board')->assertOk();
    }

    public function test_the_summary_counts_checked_in_late_checked_out_and_not_yet(): void
    {
        $ana = $this->employee('active', 'Ana');
        $bekele = $this->employee('active', 'Bekele');
        $chaltu = $this->employee('active', 'Chaltu');
        $this->employee('active', 'Dawit');

        $this->record($ana, '2026-10-30 06:00:00');
        $this->record($bekele, '2026-10-30 06:20:00', null, 'late');
        $this->record($chaltu, '2026-10-30 06:00:00', '2026-10-30 14:00:00');

        $this->actingAs($this->hr())->get('/attendance/board')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Attendance/Board')
                ->where('summary.expected', 4)
                ->where('summary.checked_in', 3)
                ->where('summary.late', 1)
                ->where('summary.checked_out', 1)
                ->where('summary.not_in', 1));
    }

    public function test_each_row_shows_its_state_and_times_in_business_time(): void
    {
        $ana = $this->employee('active', 'Ana');
        $bekele = $this->employee('active', 'Bekele');
        $this->employee('active', 'Chaltu');

        $this->record($ana, '2026-10-30 06:00:00', '2026-10-30 14:00:00');
        $this->record($bekele, '2026-10-30 06:20:00', null, 'late');

        $this->actingAs($this->hr())->get('/attendance/board')
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.data.0.name', 'Ana Tesfaye')
                ->where('rows.data.0.state', 'out')
                ->where('rows.data.0.check_in', '09:00')
                ->where('rows.data.0.check_out', '17:00')
                ->where('rows.data.0.worked', '8h 00m')
                ->where('rows.data.0.late', false)
                ->where('rows.data.1.state', 'in')
                ->where('rows.data.1.late', true)
                ->where('rows.data.1.check_in', '09:20')
                ->where('rows.data.2.state', 'not_in')
                ->where('rows.data.2.check_in', null));
    }

    public function test_only_active_employees_are_expected_and_leavers_show_on_their_working_days(): void
    {
        $left = $this->employee('terminated', 'Left');
        $this->employee('terminated', 'Gone');
        $this->employee('on_leave', 'Off');

        $this->record($left, '2026-10-30 06:00:00');

        $this->actingAs($this->hr())->get('/attendance/board')
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.expected', 1)
                ->where('summary.checked_in', 1)
                ->where('summary.not_in', 0)
                ->where('rows.data.0.name', 'Left Tesfaye'));
    }

    public function test_an_employee_is_not_expected_before_the_hire_date(): void
    {
        $this->employee('active', 'Newcomer', '2026-11-15');

        $hr = $this->hr();

        $this->actingAs($hr)->get('/attendance/board')
            ->assertInertia(fn (Assert $page) => $page->where('summary.expected', 0));

        $this->actingAs($hr)->get('/attendance/board?date=2026-11-20')
            ->assertInertia(fn (Assert $page) => $page->where('summary.expected', 1));
    }

    public function test_the_board_can_be_filtered_by_department(): void
    {
        $finance = $this->position('Finance', 'Accountant');
        $operations = $this->position('Operations', 'Clerk');

        $this->employee('active', 'Fina', '2026-01-05', $finance);
        $this->employee('active', 'Opal', '2026-01-05', $operations);

        $this->actingAs($this->hr())->get('/attendance/board?department='.$finance->department_id)
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.expected', 1)
                ->where('rows.data.0.name', 'Fina Tesfaye'));
    }

    public function test_another_day_can_be_shown(): void
    {
        $ana = $this->employee('active', 'Ana');

        $this->record($ana, '2026-10-29 06:00:00', null, 'present', '2026-10-29');

        $hr = $this->hr();

        $this->actingAs($hr)->get('/attendance/board?date=2026-10-29')
            ->assertInertia(fn (Assert $page) => $page
                ->where('date', '2026-10-29')
                ->where('summary.checked_in', 1)
                ->where('rows.data.0.check_in', '09:00'));

        $this->actingAs($hr)->get('/attendance/board')
            ->assertInertia(fn (Assert $page) => $page->where('summary.checked_in', 0));
    }

    public function test_the_default_date_is_the_business_day_not_the_utc_day(): void
    {
        // 22:30 UTC on the 30th is already 01:30 on the 31st in Addis Ababa.
        $this->travelTo(Carbon::parse('2026-10-30 22:30:00', 'UTC'));

        $this->actingAs($this->hr())->get('/attendance/board')
            ->assertInertia(fn (Assert $page) => $page
                ->where('date', '2026-10-31')
                ->where('today', '2026-10-31'));
    }

    public function test_a_badly_written_date_is_rejected(): void
    {
        $this->actingAs($this->hr())->get('/attendance/board?date=31-10-2026')->assertSessionHasErrors('date');
    }
}