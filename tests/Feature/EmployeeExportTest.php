<?php

namespace Tests\Feature;

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

class EmployeeExportTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
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

    private function employee(array $overrides = []): Employee
    {
        $this->counter++;

        $department = Department::firstOrCreate(['name' => 'Operations']);
        $position = Position::firstOrCreate(['department_id' => $department->id, 'title' => 'Clerk']);

        return Employee::create(array_merge([
            'position_id' => $position->id,
            'employee_number' => 'T-'.$this->counter,
            'first_name' => 'Ana',
            'last_name' => 'Tesfaye',
            'phone' => '+251 911 000000',
            'hire_date' => '2026-01-05',
            'employment_status' => 'active',
        ], $overrides));
    }

    /** @return array<int, array<int, string|null>> every line of the file as a list of cells */
    private function rows(TestResponse $response): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $response->streamedContent());

        $lines = array_values(array_filter(preg_split('/\r?\n/', $content), fn ($line) => $line !== ''));

        return array_map(fn ($line) => str_getcsv($line, ',', '"', ''), $lines);
    }

    public function test_guests_and_users_without_the_permission_are_refused(): void
    {
        $this->get('/employees/export')->assertRedirect('/login');

        // A Manager may see the list but may not download everyone's personal data.
        $this->actingAs($this->userWithRole('Manager'))->get('/employees/export')->assertForbidden();
        $this->actingAs($this->userWithRole('Sales Clerk'))->get('/employees/export')->assertForbidden();
    }

    public function test_hr_downloads_a_csv_with_a_header_and_one_row_per_employee(): void
    {
        $this->employee();

        $response = $this->actingAs($this->hr())->get('/employees/export');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));

        $rows = $this->rows($response);

        $this->assertCount(2, $rows);
        $this->assertSame(
            ['Employee number', 'First name', 'Last name', 'Phone', 'Hire date', 'Position', 'Department', 'Status'],
            $rows[0],
        );
        $this->assertSame(
            ['T-1', 'Ana', 'Tesfaye', '+251 911 000000', '2026-01-05', 'Clerk', 'Operations', 'active'],
            $rows[1],
        );
    }

    public function test_terminated_employees_stay_in_the_file(): void
    {
        $this->employee();
        $this->employee(['employment_status' => 'terminated']);

        $this->assertCount(3, $this->rows($this->actingAs($this->hr())->get('/employees/export')));
    }

    public function test_the_list_filters_apply_to_the_file(): void
    {
        $this->employee(['employment_status' => 'active']);
        $this->employee(['employment_status' => 'suspended', 'first_name' => 'Bekele']);

        $rows = $this->rows($this->actingAs($this->hr())->get('/employees/export?status=suspended'));

        $this->assertCount(2, $rows);
        $this->assertSame('Bekele', $rows[1][1]);
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $this->actingAs($this->hr())->get('/employees/export?status=fired')->assertSessionHasErrors('status');
    }

    public function test_cells_that_could_run_as_formulas_are_neutralised(): void
    {
        $this->employee(['first_name' => '=HYPERLINK("http://example.test")', 'last_name' => '@cmd']);

        $rows = $this->rows($this->actingAs($this->hr())->get('/employees/export'));

        $this->assertSame("'=HYPERLINK(\"http://example.test\")", $rows[1][1]);
        $this->assertSame("'@cmd", $rows[1][2]);
        // A normal phone number is left exactly as it was typed.
        $this->assertSame('+251 911 000000', $rows[1][3]);
    }

    public function test_every_export_is_audited_with_the_row_count_and_filters(): void
    {
        $this->employee();
        $this->employee(['employment_status' => 'suspended']);

        $this->actingAs($this->hr())->get('/employees/export?status=suspended')->streamedContent();

        $row = AuditLog::where('action', 'employee.exported')->firstOrFail();

        $this->assertSame(1, $row->new_values['rows']);
        $this->assertSame(['status' => 'suspended'], $row->new_values['filters']);
    }

    public function test_the_file_name_uses_the_business_date(): void
    {
        // 23:30 UTC on the 30th is already 02:30 on the 31st in Addis Ababa.
        $this->travelTo(Carbon::parse('2026-10-30 23:30:00', 'UTC'));

        $response = $this->actingAs($this->hr())->get('/employees/export');

        $this->assertStringContainsString('employees-2026-10-31.csv', $response->headers->get('content-disposition'));
    }
}