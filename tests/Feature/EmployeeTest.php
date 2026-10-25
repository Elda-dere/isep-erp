<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\QrCode;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

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

    private function hr(): User
    {
        return $this->userWithRole('HR Manager');
    }

    private function position(string $department = 'Operations', string $title = 'Clerk'): Position
    {
        $dept = Department::firstOrCreate(['name' => $department]);

        return Position::firstOrCreate(['department_id' => $dept->id, 'title' => $title]);
    }

    /** Test numbers start with T- so they never clash with the automatic EMP- numbers. */
    private function employee(array $overrides = []): Employee
    {
        $this->counter++;

        return Employee::create(array_merge([
            'position_id' => $this->position()->id,
            'employee_number' => 'T-'.$this->counter,
            'first_name' => 'Ana',
            'last_name' => 'Tesfaye',
            'hire_date' => '2026-01-05',
            'employment_status' => 'active',
        ], $overrides));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Bekele',
            'last_name' => 'Alemu',
            'phone' => '+251 911 000000',
            'hire_date' => '2026-03-01',
            'position_id' => $this->position()->id,
        ], $overrides);
    }

    private function qrCodeFor(Employee $employee): QrCode
    {
        return QrCode::create([
            'employee_id' => $employee->id,
            'token_hash' => hash('sha256', 'token-'.$employee->id.'-'.microtime(true).random_int(1, 999999)),
            'expires_at' => now()->addMinute(),
            'created_at' => now(),
        ]);
    }

    public function test_guests_and_users_without_the_permission_are_refused(): void
    {
        $this->get('/employees')->assertRedirect('/login');

        $this->actingAs($this->userWithRole('Sales Clerk'))->get('/employees')->assertForbidden();
    }

    public function test_a_viewer_can_list_but_cannot_change_anything(): void
    {
        $viewer = $this->userWithRole('Manager'); // has employees.view only
        $employee = $this->employee();

        $this->actingAs($viewer)->get('/employees')->assertOk();
        $this->actingAs($viewer)->get('/employees/create')->assertForbidden();
        $this->actingAs($viewer)->post('/employees', $this->payload())->assertForbidden();
        $this->actingAs($viewer)->put("/employees/{$employee->id}", $this->payload())->assertForbidden();
        $this->actingAs($viewer)->patch("/employees/{$employee->id}/status", ['status' => 'suspended'])->assertForbidden();
    }

    public function test_the_list_shows_position_and_department(): void
    {
        $this->employee();

        $this->actingAs($this->hr())->get('/employees')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Employees/Index')
                ->where('employees.total', 1)
                ->where('employees.data.0.position', 'Clerk')
                ->where('employees.data.0.department', 'Operations'));
    }

    public function test_the_list_can_be_filtered(): void
    {
        $clerk = $this->position('Operations', 'Clerk');
        $accountant = $this->position('Finance', 'Accountant');

        $this->employee(['position_id' => $clerk->id]);
        $this->employee(['position_id' => $accountant->id, 'employment_status' => 'suspended']);

        $hr = $this->hr();

        $this->actingAs($hr)->get('/employees?status=suspended')
            ->assertInertia(fn (Assert $page) => $page->where('employees.total', 1));

        $this->actingAs($hr)->get('/employees?department='.$accountant->department_id)
            ->assertInertia(fn (Assert $page) => $page->where('employees.total', 1));

        $this->actingAs($hr)->get('/employees?position='.$clerk->id)
            ->assertInertia(fn (Assert $page) => $page->where('employees.total', 1));
    }

    public function test_the_list_can_be_searched(): void
    {
        $this->employee(['first_name' => 'Ana', 'last_name' => 'Tesfaye', 'employee_number' => 'ZZ-100']);
        $this->employee(['first_name' => 'Bekele', 'last_name' => 'Alemu']);

        $hr = $this->hr();

        $this->actingAs($hr)->get('/employees?search=alemu')
            ->assertInertia(fn (Assert $page) => $page->where('employees.total', 1));

        $this->actingAs($hr)->get('/employees?search=ZZ-100')
            ->assertInertia(fn (Assert $page) => $page->where('employees.total', 1));

        $this->actingAs($hr)->get('/employees?search=Ana Tes')
            ->assertInertia(fn (Assert $page) => $page->where('employees.total', 1));
    }

    public function test_the_list_can_be_sorted_and_rejects_unknown_columns(): void
    {
        $this->employee(['first_name' => 'Ana', 'last_name' => 'Tesfaye']);
        $this->employee(['first_name' => 'Bekele', 'last_name' => 'Alemu']);

        $hr = $this->hr();

        $this->actingAs($hr)->get('/employees?sort=name&direction=asc')
            ->assertInertia(fn (Assert $page) => $page->where('employees.data.0.name', 'Bekele Alemu'));

        $this->actingAs($hr)->get('/employees?sort=password')->assertSessionHasErrors('sort');
    }

    public function test_the_create_page_suggests_the_next_number(): void
    {
        $this->actingAs($this->hr())->get('/employees/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Employees/Form')
                ->where('suggestedNumber', 'EMP-0001'));
    }

    public function test_numbers_are_assigned_in_order(): void
    {
        $hr = $this->hr();

        $this->actingAs($hr)->post('/employees', $this->payload())->assertSessionHasNoErrors();
        $this->actingAs($hr)->post('/employees', $this->payload(['first_name' => 'Second']))->assertSessionHasNoErrors();

        $this->assertSame(['EMP-0001', 'EMP-0002'], Employee::orderBy('id')->pluck('employee_number')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'employee.created']);
    }

    public function test_the_next_number_follows_the_highest_one(): void
    {
        $hr = $this->hr();

        $this->actingAs($hr)->post('/employees', $this->payload(['employee_number' => 'EMP-0500']))->assertSessionHasNoErrors();
        $this->actingAs($hr)->post('/employees', $this->payload())->assertSessionHasNoErrors();

        $this->assertSame(['EMP-0500', 'EMP-0501'], Employee::orderBy('id')->pluck('employee_number')->all());
    }

    public function test_a_duplicate_number_is_rejected(): void
    {
        $this->employee(['employee_number' => 'EMP-0007']);

        $this->actingAs($this->hr())->post('/employees', $this->payload(['employee_number' => 'EMP-0007']))
            ->assertSessionHasErrors('employee_number');

        $this->assertSame(1, Employee::count());
    }

    public function test_required_fields_are_checked(): void
    {
        $this->actingAs($this->hr())->post('/employees', [])
            ->assertSessionHasErrors(['first_name', 'last_name', 'hire_date', 'position_id']);

        $this->assertSame(0, Employee::count());
    }

    public function test_phone_and_position_are_checked(): void
    {
        $this->actingAs($this->hr())->post('/employees', $this->payload([
            'phone' => 'not a phone',
            'position_id' => 999999,
        ]))->assertSessionHasErrors(['phone', 'position_id']);
    }

    public function test_a_login_account_can_belong_to_only_one_employee(): void
    {
        $login = User::factory()->create();
        $this->employee(['user_id' => $login->id]);

        $this->actingAs($this->hr())->post('/employees', $this->payload(['user_id' => $login->id]))
            ->assertSessionHasErrors('user_id');

        $this->assertSame(1, Employee::count());
    }

    public function test_update_changes_fields_ignores_the_number_and_is_audited(): void
    {
        $employee = $this->employee(['employee_number' => 'EMP-0042']);
        $other = $this->position('Finance', 'Accountant');

        $this->actingAs($this->hr())->put("/employees/{$employee->id}", $this->payload([
            'first_name' => 'Changed',
            'position_id' => $other->id,
            'employee_number' => 'HACKED',
            'employment_status' => 'terminated',
        ]))->assertSessionHasNoErrors();

        $employee->refresh();

        $this->assertSame('Changed', $employee->first_name);
        $this->assertSame($other->id, $employee->position_id);
        $this->assertSame('EMP-0042', $employee->employee_number);
        $this->assertSame('active', $employee->employment_status);

        $row = AuditLog::where('action', 'employee.updated')->firstOrFail();

        $this->assertSame('Ana', $row->old_values['first_name']);
        $this->assertSame('Changed', $row->new_values['first_name']);
    }

    public function test_saving_without_changes_writes_no_audit_row(): void
    {
        $employee = $this->employee();

        $this->actingAs($this->hr())->put("/employees/{$employee->id}", [
            'first_name' => 'Ana',
            'last_name' => 'Tesfaye',
            'phone' => null,
            'hire_date' => '2026-01-05',
            'position_id' => $employee->position_id,
            'user_id' => null,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('audit_logs', ['action' => 'employee.updated']);
    }

    public function test_suspending_revokes_the_employees_qr_codes(): void
    {
        $employee = $this->employee();
        $colleague = $this->employee();

        $this->qrCodeFor($employee);
        $this->qrCodeFor($colleague);

        $this->actingAs($this->hr())->patch("/employees/{$employee->id}/status", ['status' => 'suspended'])
            ->assertSessionHasNoErrors();

        $this->assertSame('suspended', $employee->fresh()->employment_status);
        $this->assertSame(0, QrCode::where('employee_id', $employee->id)->whereNull('revoked_at')->count());
        $this->assertSame(1, QrCode::where('employee_id', $colleague->id)->whereNull('revoked_at')->count());

        $row = AuditLog::where('action', 'employee.status_changed')->firstOrFail();

        $this->assertSame('active', $row->old_values['employment_status']);
        $this->assertSame('suspended', $row->new_values['employment_status']);
        $this->assertSame(1, $row->new_values['qr_codes_revoked']);
    }

    public function test_moving_to_on_leave_keeps_the_qr_codes(): void
    {
        $employee = $this->employee();
        $this->qrCodeFor($employee);

        $this->actingAs($this->hr())->patch("/employees/{$employee->id}/status", ['status' => 'on_leave'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, QrCode::where('employee_id', $employee->id)->whereNull('revoked_at')->count());
    }

    public function test_an_invalid_status_is_rejected(): void
    {
        $employee = $this->employee();

        $this->actingAs($this->hr())->patch("/employees/{$employee->id}/status", ['status' => 'fired'])
            ->assertSessionHasErrors('status');

        $this->assertSame('active', $employee->fresh()->employment_status);
    }

    public function test_a_terminated_employee_stays_in_the_list(): void
    {
        $employee = $this->employee();

        $hr = $this->hr();

        $this->actingAs($hr)->patch("/employees/{$employee->id}/status", ['status' => 'terminated'])
            ->assertSessionHasNoErrors();

        $this->actingAs($hr)->get('/employees')
            ->assertInertia(fn (Assert $page) => $page
                ->where('employees.total', 1)
                ->where('employees.data.0.status', 'terminated'));
    }

    public function test_no_route_can_delete_an_employee(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'employees')) {
                $this->assertNotContains('DELETE', $route->methods());
            }
        }
    }
}