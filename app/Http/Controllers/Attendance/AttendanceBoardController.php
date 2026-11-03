<?php

namespace App\Http\Controllers\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Support\AttendanceFormat;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceBoardController
{
    public function index(Request $request): Response
    {
        $zone = config('erp.timezone');

        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'department' => ['nullable', 'integer'],
        ]);

        // "Today" is the business day, not the UTC day.
        $today = now()->setTimezone($zone)->toDateString();
        $date = $filters['date'] ?? $today;

        // Expected: active employees already hired that day, plus anyone who has a record that day
        // (a person terminated later still shows on the days they worked).
        $employees = Employee::query()
            ->where(function ($query) use ($date) {
                $query
                    ->where(fn ($q) => $q->where('employment_status', 'active')->where('hire_date', '<=', $date))
                    ->orWhereIn('id', AttendanceRecord::query()->where('attendance_date', $date)->select('employee_id'));
            })
            ->when($filters['department'] ?? null, fn ($q, $value) => $q
                ->whereHas('position', fn ($p) => $p->where('department_id', $value)));

        $records = AttendanceRecord::query()
            ->where('attendance_date', $date)
            ->whereIn('employee_id', (clone $employees)->select('employees.id'));

        // The numbers cover everyone in the filter, not only the rows on this page.
        $summary = [
            'expected' => (clone $employees)->count(),
            'checked_in' => (clone $records)->count(),
            'late' => (clone $records)->where('status', 'late')->count(),
            'checked_out' => (clone $records)->whereNotNull('check_out_at')->count(),
        ];
        $summary['not_in'] = $summary['expected'] - $summary['checked_in'];

        $rows = $employees
            ->with('position.department')
            ->orderBy('employee_number')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        $byEmployee = AttendanceRecord::query()
            ->where('attendance_date', $date)
            ->whereIn('employee_id', $rows->pluck('id'))
            ->get()
            ->keyBy('employee_id');

        $rows->through(function (Employee $employee) use ($byEmployee) {
            $record = $byEmployee->get($employee->id);

            return [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'name' => $employee->fullName(),
                'position' => $employee->position->title,
                'department' => $employee->position->department->name,
                'employment_status' => $employee->employment_status,
                'state' => $record === null ? 'not_in' : ($record->check_out_at === null ? 'in' : 'out'),
                'late' => $record?->status === 'late',
                'check_in' => AttendanceFormat::time($record?->check_in_at),
                'check_out' => AttendanceFormat::time($record?->check_out_at),
                'worked' => AttendanceFormat::worked($record?->check_in_at, $record?->check_out_at),
            ];
        });

        return Inertia::render('Attendance/Board', [
            'rows' => $rows,
            'summary' => $summary,
            'date' => $date,
            'today' => $today,
            'timezone' => $zone,
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['date', 'department']),
        ]);
    }
}