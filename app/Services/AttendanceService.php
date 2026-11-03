<?php

namespace App\Services;

use App\Models\ApprovedNetwork;
use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\QrCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    /** One distinct message for every way a scan can fail (ATT-03). */
    public const MESSAGES = [
        'unknown_token' => 'This code is not recognised. Ask the employee to refresh their code.',
        'expired' => 'This code has expired. Ask the employee to show a fresh code.',
        'revoked' => 'This code was already used or cancelled. Ask the employee for a fresh code.',
        'employee_inactive' => 'This employee cannot check in right now. Please contact HR.',
        'outside_network' => 'This scanner is not on an approved network.',
        'too_soon' => 'This employee scanned a moment ago. Try again in a minute.',
        'already_completed' => 'This employee has already checked in and out today.',
    ];

    /**
     * Handles one scanned code. The checks run in the order the SRS gives (ATT-03):
     * code found, not expired, not revoked, employee active, scanner network approved.
     *
     * @return array{ok: bool, code: string, message: string, employee?: string, number?: string, time?: string}
     */
    public function scan(string $token, string $ip, ?string $userAgent): array
    {
        $hash = QrTokenService::hash($token);

        return DB::transaction(function () use ($hash, $ip, $userAgent) {
            $now = now();

            // Lock the code, so two scans of the same code at the same moment cannot both succeed.
            $code = QrCode::query()->where('token_hash', $hash)->lockForUpdate()->first();

            if (! $code) {
                return $this->reject('unknown_token', null);
            }

            if ($code->expires_at->lte($now)) {
                return $this->reject('expired', $code->employee_id);
            }

            if ($code->revoked_at !== null) {
                return $this->reject('revoked', $code->employee_id);
            }

            // Lock the employee, so their record for the day cannot be created twice.
            $employee = Employee::query()->whereKey($code->employee_id)->lockForUpdate()->firstOrFail();

            if ($employee->employment_status !== 'active') {
                return $this->reject('employee_inactive', $employee->id);
            }

            // With no active network this is always false, so every scan is refused (ATT-08).
            if (! ApprovedNetwork::allows($ip)) {
                return $this->reject('outside_network', $employee->id);
            }

            // The day is the business day (Africa/Addis_Ababa). Times are stored in UTC.
            $today = $now->copy()->setTimezone(config('erp.timezone'))->toDateString();

            $record = AttendanceRecord::query()
                ->where('employee_id', $employee->id)
                ->where('attendance_date', $today)
                ->lockForUpdate()
                ->first();

            if (! $record) {
                $record = AttendanceRecord::create([
                    'employee_id' => $employee->id,
                    'attendance_date' => $today,
                    'check_in_at' => $now,
                    'check_out_at' => null,
                    'status' => 'present',
                    'created_at' => $now,
                ]);

                $this->event($record, AttendanceEvent::CHECK_IN, $now, $ip, $userAgent);
                $code->update(['revoked_at' => $now]);

                return $this->success('check_in', $employee, $now);
            }

            if ($record->check_out_at !== null) {
                return $this->reject('already_completed', $employee->id);
            }

            $gap = $now->getTimestamp() - $record->check_in_at->getTimestamp();

            if ($gap < $this->minimumGap()) {
                return $this->reject('too_soon', $employee->id);
            }

            $record->update(['check_out_at' => $now]);

            $this->event($record, AttendanceEvent::CHECK_OUT, $now, $ip, $userAgent);
            $code->update(['revoked_at' => $now]);

            return $this->success('check_out', $employee, $now);
        });
    }

    public function minimumGap(): int
    {
        return max(0, (int) config('attendance.minimum_gap_seconds', 60));
    }

    private function event(AttendanceRecord $record, string $type, Carbon $now, string $ip, ?string $userAgent): void
    {
        AttendanceEvent::create([
            'attendance_record_id' => $record->id,
            'event_type' => $type,
            'recorded_at' => $now,
            'ip_address' => $ip,
            'method' => 'qr',
            // The scanner's browser, cut to fit the column (ATT-06).
            'device_info' => $userAgent === null ? null : mb_substr($userAgent, 0, 255),
        ]);
    }

    /** @return array{ok: bool, code: string, message: string, employee: string, number: string, time: string} */
    private function success(string $action, Employee $employee, Carbon $now): array
    {
        return [
            'ok' => true,
            'code' => $action,
            'message' => $action === 'check_in' ? 'Checked in' : 'Checked out',
            'employee' => $employee->fullName(),
            'number' => $employee->employee_number,
            'time' => $now->copy()->setTimezone(config('erp.timezone'))->format('H:i:s'),
        ];
    }

    /**
     * Records the refusal in the audit log (ATT-07). The audit values hold only the reason:
     * never the code itself. The scanner's user and address are filled in automatically.
     *
     * @return array{ok: bool, code: string, message: string}
     */
    private function reject(string $reason, ?int $employeeId): array
    {
        AuditLogger::log('attendance.scan_rejected', $employeeId === null ? null : 'employee', $employeeId, null, [
            'reason' => $reason,
        ]);

        return ['ok' => false, 'code' => $reason, 'message' => self::MESSAGES[$reason]];
    }
}