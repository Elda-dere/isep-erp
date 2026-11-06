<?php

namespace Database\Seeders;

use App\Models\ApprovedNetwork;
use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Sample people and attendance for a demonstration. It is NOT part of DatabaseSeeder,
 * it refuses to run anywhere except APP_ENV=local, and running it twice creates no duplicates.
 */
class DemoSeeder extends Seeder
{
    /** email, name, role */
    private const ACCOUNTS = [
        ['hr@demo.test', 'Hana Bekele', 'HR Manager'],
        ['manager@demo.test', 'Mulu Girma', 'Manager'],
        ['scanner@demo.test', 'Gate Scanner', 'Attendance Scanner'],
        ['sales@demo.test', 'Sara Kebede', 'Sales Clerk'],
        ['warehouse@demo.test', 'Wondu Abebe', 'Warehouse Clerk'],
        ['buyer@demo.test', 'Bele Tadesse', 'Procurement Officer'],
        ['ana@demo.test', 'Ana Tesfaye', 'Employee'],
        ['bekele@demo.test', 'Bekele Alemu', 'Employee'],
    ];

    /** number, first name, last name, department, position, status, login email */
    private const PEOPLE = [
        ['DEMO-001', 'Ana', 'Tesfaye', 'Finance', 'Accountant', 'active', 'ana@demo.test'],
        ['DEMO-002', 'Bekele', 'Alemu', 'Operations', 'Clerk', 'active', 'bekele@demo.test'],
        ['DEMO-003', 'Chaltu', 'Gemechu', 'Sales', 'Cashier', 'active', null],
        ['DEMO-004', 'Dawit', 'Haile', 'Operations', 'Store keeper', 'active', null],
        ['DEMO-005', 'Eden', 'Mulugeta', 'Finance', 'Accountant', 'on_leave', null],
        ['DEMO-006', 'Fikru', 'Solomon', 'Sales', 'Cashier', 'active', null],
    ];

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('The demo data can only be created when APP_ENV=local.');
        }

        $password = (string) config('erp.demo_password');

        if (strlen($password) < 10) {
            throw new RuntimeException('Set DEMO_PASSWORD in .env (at least 10 characters), then run: php artisan config:clear');
        }

        $users = [];

        foreach (self::ACCOUNTS as [$email, $name, $roleName]) {
            $role = Role::where('name', $roleName)->first()
                ?? throw new RuntimeException("The role {$roleName} is missing. Run: php artisan db:seed");

            // Created only if missing: an account that already exists keeps its own password.
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => $password, 'is_active' => true],
            );

            $user->roles()->syncWithoutDetaching([$role->id]);
            $users[$email] = $user;
        }

        // The scanner runs on this computer, so its own addresses are approved.
        foreach (['127.0.0.1/32' => 'Demo: this computer (IPv4)', '::1/128' => 'Demo: this computer (IPv6)'] as $range => $name) {
            ApprovedNetwork::firstOrCreate(['ip_range' => $range], ['name' => $name, 'is_active' => true]);
        }

        $employees = [];

        foreach (self::PEOPLE as [$number, $first, $last, $department, $title, $status, $login]) {
            $dept = Department::firstOrCreate(['name' => $department]);
            $position = Position::firstOrCreate(['department_id' => $dept->id, 'title' => $title]);

            $employees[] = Employee::firstOrCreate(['employee_number' => $number], [
                'user_id' => $login === null ? null : $users[$login]->id,
                'position_id' => $position->id,
                'first_name' => $first,
                'last_name' => $last,
                'phone' => '+251 911 00000'.substr($number, -1),
                'hire_date' => '2026-01-05',
                'employment_status' => $status,
            ]);
        }

        $this->attendance($employees);
    }

    /** @param array<int, Employee> $employees */
    private function attendance(array $employees): void
    {
        $today = now()->setTimezone(config('erp.timezone'))->startOfDay();

        foreach ($employees as $index => $employee) {
            if ($employee->employment_status !== 'active') {
                continue;
            }

            $worked = 0;

            // The last two weeks, not including today, Monday to Friday only.
            for ($back = 1; $back <= 14; $back++) {
                $day = $today->copy()->subDays($back);

                if ($day->dayOfWeekIso > 5) {
                    continue;
                }

                $worked++;

                // A pattern, not random numbers, so the demo looks the same every time.
                $late = (($index + $back) % 5) === 0;
                $in = $day->copy()->setTime(9, 0)->addMinutes($late ? 25 : (($index * 3 + $back) % 8));

                // One forgotten check-out, so the monthly summary has something to show.
                $out = ($index === 2 && $worked === 3)
                    ? null
                    : $day->copy()->setTime(17, 0)->addMinutes(($index + $back) % 20);

                $record = AttendanceRecord::firstOrCreate(
                    ['employee_id' => $employee->id, 'attendance_date' => $day->toDateString()],
                    [
                        'check_in_at' => $in->copy()->utc(),
                        'check_out_at' => $out?->copy()->utc(),
                        'status' => $late ? 'late' : 'present',
                        'created_at' => now(),
                    ],
                );

                if (! $record->wasRecentlyCreated) {
                    continue;
                }

                $this->event($record, AttendanceEvent::CHECK_IN, $in);

                if ($out !== null) {
                    $this->event($record, AttendanceEvent::CHECK_OUT, $out);
                }
            }
        }
    }

    private function event(AttendanceRecord $record, string $type, Carbon $at): void
    {
        AttendanceEvent::create([
            'attendance_record_id' => $record->id,
            'event_type' => $type,
            'recorded_at' => $at->copy()->utc(),
            'ip_address' => '127.0.0.1',
            'method' => 'qr',
            'device_info' => 'Demo data',
        ]);
    }
}