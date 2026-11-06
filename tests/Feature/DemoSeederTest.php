<?php

namespace Tests\Feature;

use App\Models\ApprovedNetwork;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        // Friday 30 October 2026, 15:00 in Addis Ababa.
        $this->travelTo(Carbon::parse('2026-10-30 12:00:00', 'UTC'));

        config(['erp.demo_password' => 'demo-password-1']);
    }

    private function local(): void
    {
        $this->app['env'] = 'local';
    }

    public function test_it_refuses_to_run_outside_the_local_environment(): void
    {
        $this->expectException(RuntimeException::class);

        $this->seed(DemoSeeder::class);
    }

    public function test_it_refuses_a_missing_or_short_demo_password(): void
    {
        $this->local();
        config(['erp.demo_password' => 'short']);

        $this->expectException(RuntimeException::class);

        $this->seed(DemoSeeder::class);
    }

    public function test_every_demo_account_has_the_right_role_and_the_password_is_hashed(): void
    {
        $this->local();
        $this->seed(DemoSeeder::class);

        $hr = User::where('email', 'hr@demo.test')->firstOrFail();
        $scanner = User::where('email', 'scanner@demo.test')->firstOrFail();
        $ana = User::where('email', 'ana@demo.test')->firstOrFail();

        $this->assertTrue($hr->hasPermission('employees.manage'));
        $this->assertTrue($scanner->hasPermission('attendance.scan'));
        $this->assertFalse($scanner->hasPermission('employees.manage'));
        $this->assertTrue($ana->hasPermission('qr.own'));
        $this->assertNotSame('demo-password-1', $ana->password);
    }

    public function test_people_are_created_and_two_of_them_have_a_login(): void
    {
        $this->local();
        $this->seed(DemoSeeder::class);

        $this->assertSame(6, Employee::count());
        $this->assertSame(5, Employee::where('employment_status', 'active')->count());
        $this->assertSame(2, Employee::whereNotNull('user_id')->count());
        $this->assertSame(
            User::where('email', 'ana@demo.test')->value('id'),
            Employee::where('employee_number', 'DEMO-001')->value('user_id'),
        );
    }

    public function test_the_local_computer_is_an_approved_network(): void
    {
        $this->local();
        $this->seed(DemoSeeder::class);

        $this->assertTrue(ApprovedNetwork::allows('127.0.0.1'));
        $this->assertTrue(ApprovedNetwork::allows('::1'));
    }

    public function test_attendance_covers_weekdays_before_today_for_active_people_only(): void
    {
        $this->local();
        $this->seed(DemoSeeder::class);

        $this->assertGreaterThan(0, AttendanceRecord::count());
        $this->assertSame(0, AttendanceRecord::where('attendance_date', '2026-10-30')->count());
        $this->assertSame(0, AttendanceRecord::whereRaw('DAYOFWEEK(attendance_date) IN (1, 7)')->count());
        $this->assertSame(
            0,
            AttendanceRecord::where('employee_id', Employee::where('employee_number', 'DEMO-005')->value('id'))->count(),
        );
        $this->assertGreaterThan(0, AttendanceRecord::where('status', 'late')->count());
        $this->assertSame(1, AttendanceRecord::whereNull('check_out_at')->count());
    }

    public function test_running_it_twice_creates_no_duplicates(): void
    {
        $this->local();
        $this->seed(DemoSeeder::class);

        $counts = [User::count(), Employee::count(), AttendanceRecord::count(), ApprovedNetwork::count()];

        $this->seed(DemoSeeder::class);

        $this->assertSame($counts, [User::count(), Employee::count(), AttendanceRecord::count(), ApprovedNetwork::count()]);
    }
}