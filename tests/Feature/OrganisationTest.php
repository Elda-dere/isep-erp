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

class OrganisationTest extends TestCase
{
    use RefreshDatabase;

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

    private function employeeAt(Position $position): Employee
    {
        return Employee::create([
            'position_id' => $position->id,
            'employee_number' => 'T-'.uniqid(),
            'first_name' => 'Ana',
            'last_name' => 'Tesfaye',
            'hire_date' => '2026-01-05',
            'employment_status' => 'active',
        ]);
    }

    public function test_guests_and_users_without_the_permission_are_refused(): void
    {
        $this->get('/departments')->assertRedirect('/login');
        $this->get('/positions')->assertRedirect('/login');

        $clerk = $this->userWithRole('Sales Clerk');

        $this->actingAs($clerk)->get('/departments')->assertForbidden();
        $this->actingAs($clerk)->get('/positions')->assertForbidden();
        $this->actingAs($clerk)->post('/departments', ['name' => 'X'])->assertForbidden();
    }

    public function test_hr_sees_the_departments_with_their_position_counts(): void
    {
        $department = Department::create(['name' => 'Finance']);
        Position::create(['department_id' => $department->id, 'title' => 'Accountant']);

        $this->actingAs($this->hr())->get('/departments')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Departments/Index')
                ->where('departments.total', 1)
                ->where('departments.data.0.positions_count', 1));
    }

    public function test_hr_can_create_a_department_and_it_is_audited(): void
    {
        $this->actingAs($this->hr())->post('/departments', [
            'name' => 'Finance',
            'description' => 'Money matters',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('departments', ['name' => 'Finance', 'description' => 'Money matters']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'department.created']);
    }

    public function test_a_department_needs_a_name(): void
    {
        $this->actingAs($this->hr())->post('/departments', ['name' => ''])->assertSessionHasErrors('name');

        $this->assertSame(0, Department::count());
    }

    public function test_hr_can_update_a_department_and_the_change_is_audited(): void
    {
        $department = Department::create(['name' => 'Old name']);

        $this->actingAs($this->hr())->put("/departments/{$department->id}", [
            'name' => 'New name',
            'description' => null,
        ])->assertSessionHasNoErrors();

        $this->assertSame('New name', $department->fresh()->name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'department.updated', 'entity_id' => $department->id]);
    }

    public function test_a_department_with_positions_cannot_be_deleted(): void
    {
        $department = Department::create(['name' => 'Finance']);
        Position::create(['department_id' => $department->id, 'title' => 'Accountant']);

        $this->actingAs($this->hr())->delete("/departments/{$department->id}")
            ->assertSessionHasErrors('department');

        $this->assertDatabaseHas('departments', ['id' => $department->id]);
    }

    public function test_an_empty_department_can_be_deleted(): void
    {
        $department = Department::create(['name' => 'Temporary']);

        $this->actingAs($this->hr())->delete("/departments/{$department->id}")
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('departments', ['id' => $department->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'department.deleted', 'entity_id' => $department->id]);
    }

    public function test_hr_can_create_a_position_and_it_is_audited(): void
    {
        $department = Department::create(['name' => 'Finance']);

        $this->actingAs($this->hr())->post('/positions', [
            'department_id' => $department->id,
            'title' => 'Accountant',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('positions', ['department_id' => $department->id, 'title' => 'Accountant']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'position.created']);
    }

    public function test_a_position_needs_a_title_and_an_existing_department(): void
    {
        $this->actingAs($this->hr())->post('/positions', [
            'department_id' => 999999,
            'title' => '',
        ])->assertSessionHasErrors(['department_id', 'title']);

        $this->assertSame(0, Position::count());
    }

    public function test_a_position_held_by_an_employee_cannot_be_deleted(): void
    {
        $department = Department::create(['name' => 'Finance']);
        $position = Position::create(['department_id' => $department->id, 'title' => 'Accountant']);
        $this->employeeAt($position);

        $this->actingAs($this->hr())->delete("/positions/{$position->id}")
            ->assertSessionHasErrors('position');

        $this->assertDatabaseHas('positions', ['id' => $position->id]);
    }

    public function test_an_unused_position_can_be_deleted(): void
    {
        $department = Department::create(['name' => 'Finance']);
        $position = Position::create(['department_id' => $department->id, 'title' => 'Spare']);

        $this->actingAs($this->hr())->delete("/positions/{$position->id}")
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('positions', ['id' => $position->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'position.deleted', 'entity_id' => $position->id]);
    }

    public function test_positions_can_be_filtered_by_department(): void
    {
        $finance = Department::create(['name' => 'Finance']);
        $store = Department::create(['name' => 'Store']);
        Position::create(['department_id' => $finance->id, 'title' => 'Accountant']);
        Position::create(['department_id' => $store->id, 'title' => 'Keeper']);

        $this->actingAs($this->hr())->get('/positions?department='.$store->id)
            ->assertInertia(fn (Assert $page) => $page
                ->where('positions.total', 1)
                ->where('positions.data.0.title', 'Keeper'));
    }
}