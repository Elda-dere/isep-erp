<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrganisationOverviewTest extends TestCase
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

    private function employeeAt(Position $position, string $status = 'active'): Employee
    {
        $this->counter++;

        return Employee::create([
            'position_id' => $position->id,
            'employee_number' => 'T-'.$this->counter,
            'first_name' => 'Ana',
            'last_name' => 'Tesfaye',
            'hire_date' => '2026-01-05',
            'employment_status' => $status,
        ]);
    }

    public function test_guests_and_users_without_the_permission_are_refused(): void
    {
        $this->get('/organisation')->assertRedirect('/login');

        $this->actingAs($this->userWithRole('Sales Clerk'))->get('/organisation')->assertForbidden();
    }

    public function test_hr_and_managers_can_open_it(): void
    {
        $this->actingAs($this->userWithRole('HR Manager'))->get('/organisation')->assertOk();
        $this->actingAs($this->userWithRole('Manager'))->get('/organisation')->assertOk();
    }

    public function test_headcount_per_position_leaves_out_terminated_staff(): void
    {
        $finance = Department::create(['name' => 'Finance']);
        $accountant = Position::create(['department_id' => $finance->id, 'title' => 'Accountant']);
        Position::create(['department_id' => $finance->id, 'title' => 'Clerk']);

        $this->employeeAt($accountant);
        $this->employeeAt($accountant, 'on_leave');
        $this->employeeAt($accountant, 'terminated');

        $this->actingAs($this->userWithRole('HR Manager'))->get('/organisation')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Organisation/Overview')
                ->where('departments.0.name', 'Finance')
                ->where('departments.0.headcount', 2)
                ->where('departments.0.positions.0.title', 'Accountant')
                ->where('departments.0.positions.0.headcount', 2)
                ->where('departments.0.positions.1.title', 'Clerk')
                ->where('departments.0.positions.1.headcount', 0)
                ->where('headcount', 2));
    }

    public function test_a_department_without_positions_is_still_listed(): void
    {
        Department::create(['name' => 'Empty department']);

        $this->actingAs($this->userWithRole('HR Manager'))->get('/organisation')
            ->assertInertia(fn (Assert $page) => $page
                ->where('departments.0.name', 'Empty department')
                ->where('departments.0.headcount', 0)
                ->has('departments.0.positions', 0));
    }

    public function test_employees_by_status_lists_all_four_statuses(): void
    {
        $department = Department::create(['name' => 'Operations']);
        $position = Position::create(['department_id' => $department->id, 'title' => 'Clerk']);

        $this->employeeAt($position, 'active');
        $this->employeeAt($position, 'active');
        $this->employeeAt($position, 'terminated');

        $this->actingAs($this->userWithRole('HR Manager'))->get('/organisation')
            ->assertInertia(fn (Assert $page) => $page
                ->has('statuses', 4)
                ->where('statuses.0.status', 'active')
                ->where('statuses.0.total', 2)
                ->where('statuses.1.status', 'on_leave')
                ->where('statuses.1.total', 0)
                ->where('statuses.3.status', 'terminated')
                ->where('statuses.3.total', 1));
    }
}