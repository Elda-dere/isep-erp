<?php

namespace App\Http\Controllers\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Support\AttendanceFormat;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyAttendanceController
{
    public function index(Request $request): Response
    {
        $employee = Employee::query()->where('user_id', $request->user()->id)->first();

        if (! $employee) {
            return Inertia::render('Attendance/MyAttendance', [
                'state' => 'no_employee',
                'message' => 'Your login is not linked to an employee record. Ask HR to link it.',
            ]);
        }

        $zone = config('erp.timezone');
        $today = now()->setTimezone($zone)->toDateString();

        $todayRecord = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->where('attendance_date', $today)
            ->first();

        return Inertia::render('Attendance/MyAttendance', [
            'state' => 'ready',
            'employeeName' => $employee->fullName(),
            'timezone' => $zone,
            'today' => [
                'date' => $today,
                'state' => $todayRecord === null ? 'not_in' : ($todayRecord->check_out_at === null ? 'in' : 'out'),
                'late' => $todayRecord?->status === 'late',
                'check_in' => AttendanceFormat::time($todayRecord?->check_in_at),
                'check_out' => AttendanceFormat::time($todayRecord?->check_out_at),
                'worked' => AttendanceFormat::worked($todayRecord?->check_in_at, $todayRecord?->check_out_at),
            ],
            'records' => AttendanceRecord::query()
                ->where('employee_id', $employee->id)
                ->orderByDesc('attendance_date')
                ->orderByDesc('id')
                ->paginate(25)
                ->through(fn (AttendanceRecord $record) => [
                    'id' => $record->id,
                    'date' => $record->attendance_date->format('Y-m-d'),
                    'check_in' => AttendanceFormat::time($record->check_in_at),
                    'check_out' => AttendanceFormat::time($record->check_out_at),
                    'worked' => AttendanceFormat::worked($record->check_in_at, $record->check_out_at),
                    'status' => $record->status,
                ]),
        ]);
    }
}