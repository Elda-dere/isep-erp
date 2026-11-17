<?php

namespace App\Http\Controllers\Attendance;

use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\QrCode;
use App\Services\AttendanceService;
use App\Services\QrTokenService;
use App\Support\AttendanceFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CheckInController
{
    public function __construct(private AttendanceService $attendance) {}

    public function handle(Request $request): Response
    {
        $raw = $request->query('token') ?? $request->input('token');
        $token = is_string($raw) ? trim($raw) : '';

        // Extract 64-hex token if a URL was passed
        if (preg_match('/([0-9a-f]{64})/i', $token, $matches)) {
            $token = $matches[1];
        }

        $user = $request->user();
        $employee = Employee::query()
            ->with(['position.department'])
            ->where('user_id', $user->id)
            ->first();

        if (! $employee) {
            return Inertia::render('Attendance/CheckInResult', [
                'ok' => false,
                'message' => 'Your account is not linked to an employee profile. Please contact HR.',
            ]);
        }

        if ($employee->employment_status !== 'active') {
            return Inertia::render('Attendance/CheckInResult', [
                'ok' => false,
                'employee' => $employee->fullName(),
                'message' => 'Your employment status is inactive. Attendance cannot be recorded.',
            ]);
        }

        $settings = AttendanceSetting::current();

        // ─────────────────────────────────────────────────────────────
        // 1. Static Station QR Check-In
        // ─────────────────────────────────────────────────────────────
        if (! empty($token) && hash_equals((string) $settings->static_qr_token, $token)) {
            if (! $settings->static_qr_enabled) {
                return Inertia::render('Attendance/CheckInResult', [
                    'ok' => false,
                    'message' => 'Static QR station check-in is currently disabled by administrator.',
                ]);
            }

            return $this->processStaticCheckIn($employee, $request);
        }

        // ─────────────────────────────────────────────────────────────
        // 2. Personal / Dynamic QR Check-In
        // ─────────────────────────────────────────────────────────────
        if (! empty($token)) {
            $result = $this->attendance->scan($token, (string) $request->ip(), $request->userAgent());

            return Inertia::render('Attendance/CheckInResult', [
                'ok' => $result['ok'],
                'message' => $result['message'],
                'employee' => $result['employee'] ?? $employee->fullName(),
                'number' => $result['number'] ?? $employee->employee_number,
                'time' => $result['time'] ?? now()->setTimezone(config('erp.timezone'))->format('H:i'),
                'department' => $employee->position?->department?->name ?? 'Staff',
            ]);
        }

        return Inertia::render('Attendance/CheckInResult', [
            'ok' => false,
            'message' => 'No attendance token provided in QR code.',
        ]);
    }

    private function processStaticCheckIn(Employee $employee, Request $request): Response
    {
        $now = now();
        $zone = config('erp.timezone');
        $today = $now->copy()->setTimezone($zone)->toDateString();

        return DB::transaction(function () use ($employee, $now, $today, $request, $zone) {
            $record = AttendanceRecord::query()
                ->where('employee_id', $employee->id)
                ->where('attendance_date', $today)
                ->lockForUpdate()
                ->first();

            $statusText = 'present';
            $cutoff = $now->copy()->setTimezone($zone)->setTime(9, 15);
            if ($now->copy()->setTimezone($zone)->gt($cutoff)) {
                $statusText = 'late';
            }

            if (! $record) {
                $record = AttendanceRecord::create([
                    'employee_id' => $employee->id,
                    'attendance_date' => $today,
                    'check_in_at' => $now,
                    'check_out_at' => null,
                    'status' => $statusText,
                    'created_at' => $now,
                ]);

                AttendanceEvent::create([
                    'attendance_record_id' => $record->id,
                    'event_type' => AttendanceEvent::CHECK_IN,
                    'recorded_at' => $now,
                    'ip_address' => (string) $request->ip(),
                    'method' => 'static_qr',
                    'device_info' => $request->userAgent(),
                ]);

                return Inertia::render('Attendance/CheckInResult', [
                    'ok' => true,
                    'action' => 'check_in',
                    'message' => 'Check-in recorded successfully!',
                    'employee' => $employee->fullName(),
                    'number' => $employee->employee_number,
                    'time' => AttendanceFormat::time($now),
                    'late' => $statusText === 'late',
                    'department' => $employee->position?->department?->name ?? 'Staff',
                ]);
            }

            if ($record->check_out_at !== null) {
                return Inertia::render('Attendance/CheckInResult', [
                    'ok' => false,
                    'message' => 'You have already checked in and checked out for today.',
                    'employee' => $employee->fullName(),
                ]);
            }

            // Cooldown check (minimum 2 minutes between in and out)
            $diff = $now->getTimestamp() - $record->check_in_at->getTimestamp();
            if ($diff < 120) {
                return Inertia::render('Attendance/CheckInResult', [
                    'ok' => false,
                    'message' => 'You checked in just a moment ago. Please wait a few minutes before checking out.',
                    'employee' => $employee->fullName(),
                ]);
            }

            $record->update(['check_out_at' => $now]);

            AttendanceEvent::create([
                'attendance_record_id' => $record->id,
                'event_type' => AttendanceEvent::CHECK_OUT,
                'recorded_at' => $now,
                'ip_address' => (string) $request->ip(),
                'method' => 'static_qr',
                'device_info' => $request->userAgent(),
            ]);

            return Inertia::render('Attendance/CheckInResult', [
                'ok' => true,
                'action' => 'check_out',
                'message' => 'Check-out recorded successfully! Shift completed.',
                'employee' => $employee->fullName(),
                'number' => $employee->employee_number,
                'time' => AttendanceFormat::time($now),
                'worked' => AttendanceFormat::worked($record->check_in_at, $now),
                'department' => $employee->position?->department?->name ?? 'Staff',
            ]);
        });
    }
}
