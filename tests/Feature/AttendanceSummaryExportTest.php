<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AttendanceSummaryExportTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

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

    private function record(Employee $employee, string $date, string $inUtc, ?string $outUtc = null, string $status = 'present'): void
    {
        AttendanceRecord::create([
            'employee_id' => $employee->id,
            'attendance_date' => $date,
            'check_in_at' => Carbon::parse($inUtc, 'UTC'),
            'check_out_at' => $outUtc === null ? null : Carbon::parse($outUtc, 'UTC'),
            'status' => $status,
            'created_at' => now(),
        ]);
    }

    /** @return array<int, array<int, string|null>> */
    private function rows(TestResponse $response): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $response->streamedContent());
        $lines = array_values(array_filter(preg_split('/\r?\n/', $content), fn ($line) => $line !== ''));

        return array_map(fn ($line) => str_getcsv($line, ',', '"', ''), $lines);
    }

    public function test_guests_and_users_without_the_permission_are_refused(): void
    {
        $this->get('/attendance/summary/export')->assertRedirect('/login');

        // A Manager may read the page but may not download everyone's data.
        foreach (['Manager', 'Employee', 'Attendance Scanner', 'Sales Clerk'] as $role) {
            $this->actingAs($this->userWithRole($role))->get('/attendance/summary/export')->assertForbidden();
        }
    }

    public function test_hr_downloads_the_month_as_csv(): void
    {
        $ana = $this->employee();

        $this->record($ana, '2026-10-01', '2026-10-01 06:00:00', '2026-10-01 14:05:00');
        $this->record($ana, '2026-10-02', '2026-10-02 06:20:00', '2026-10-02 14:00:00', 'late');
        $this->record($ana, '2026-10-05', '2026-10-05 06:00:00');

        $response = $this->actingAs($this->hr())->get('/attendance/summary/export');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));

        $rows = $this->rows($response);

        $this->assertSame([
            'Employee number', 'Name', 'Department', 'Position', 'Status',
            'Days worked', 'Late days', 'Hours worked', 'Missing check-outs', 'Absent days',
        ], $rows[0]);

        $this->assertSame(
            ['T-1', 'Ana Tesfaye', 'Operations', 'Clerk', 'active', '3', '1', '15.75', '1', '18'],
            $rows[1],
        );
    }

    public function test_the_file_is_named_after_the_month(): void
    {
        $response = $this->actingAs($this->hr())->get('/attendance/summary/export?month=2026-09');

        $this->assertStringContainsString('attendance-summary-2026-09.csv', $response->headers->get('content-disposition'));
    }

    public function test_cells_that_could_run_as_formulas_are_neutralised(): void
    {
        $this->employee('active', '=HYPERLINK("http://example.test")');

        $rows = $this->rows($this->actingAs($this->hr())->get('/attendance/summary/export'));

        $this->assertSame("'=HYPERLINK(\"http://example.test\") Tesfaye", $rows[1][1]);
    }

    public function test_absence_is_an_empty_cell_when_it_cannot_be_worked_out(): void
    {
        $leave = $this->employee('on_leave');
        $this->record($leave, '2026-10-01', '2026-10-01 06:00:00', '2026-10-01 14:00:00');

        $rows = $this->rows($this->actingAs($this->hr())->get('/attendance/summary/export'));

        $this->assertSame('', $rows[1][9]);
        $this->assertSame('1', $rows[1][5]);
    }

    public function test_every_export_is_audited(): void
    {
        $this->employee();
        $this->employee('active', 'Bekele');

        $this->actingAs($this->hr())->get('/attendance/summary/export?month=2026-09')->streamedContent();

        $row = AuditLog::where('action', 'attendance.summary_exported')->firstOrFail();

        $this->assertEquals(['month' => '2026-09', 'rows' => 2, 'department' => null], $row->new_values);
    }

    public function test_a_future_month_is_refused(): void
    {
        $this->actingAs($this->hr())->get('/attendance/summary/export?month=2026-11')->assertSessionHasErrors('month');
    }
}