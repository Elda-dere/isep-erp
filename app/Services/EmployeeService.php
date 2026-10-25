<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\QrCode;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class EmployeeService
{
    public const PREFIX = 'EMP-';

    /** @param array<string, mixed> $data */
    public function create(array $data): Employee
    {
        $auto = blank($data['employee_number'] ?? null);

        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($data, $auto) {
                    if ($auto) {
                        $data['employee_number'] = $this->nextNumber(lock: true);
                    }

                    $employee = Employee::create([
                        'user_id' => $data['user_id'] ?? null,
                        'position_id' => $data['position_id'],
                        'employee_number' => $data['employee_number'],
                        'first_name' => $data['first_name'],
                        'last_name' => $data['last_name'],
                        'phone' => $data['phone'] ?? null,
                        'hire_date' => $data['hire_date'],
                        'employment_status' => $data['employment_status'] ?? 'active',
                    ]);

                    AuditLogger::log('employee.created', 'employee', $employee->id, null, $this->snapshot($employee->refresh()));

                    return $employee;
                });
            } catch (UniqueConstraintViolationException $exception) {
                // Two people saved at the same moment and drew the same number: draw again.
                if (! $auto || $attempt >= 3) {
                    throw $exception;
                }
            }
        }
    }

    /** @param array<string, mixed> $data */
    public function update(Employee $employee, array $data): Employee
    {
        return DB::transaction(function () use ($employee, $data) {
            $old = $this->snapshot($employee);

            // The employee number and the status are not changed here.
            $employee->update([
                'user_id' => $data['user_id'] ?? null,
                'position_id' => $data['position_id'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'] ?? null,
                'hire_date' => $data['hire_date'],
            ]);

            $new = $this->snapshot($employee->refresh());

            $changedOld = [];
            $changedNew = [];

            foreach ($new as $key => $value) {
                if ($old[$key] !== $value) {
                    $changedOld[$key] = $old[$key];
                    $changedNew[$key] = $value;
                }
            }

            if ($changedNew !== []) {
                AuditLogger::log('employee.updated', 'employee', $employee->id, $changedOld, $changedNew);
            }

            return $employee;
        });
    }

    public function changeStatus(Employee $employee, string $status): void
    {
        DB::transaction(function () use ($employee, $status) {
            $old = $employee->employment_status;

            if ($old === $status) {
                return;
            }

            $employee->update(['employment_status' => $status]);

            $revoked = 0;

            // Suspended or terminated staff lose their QR codes at once (EMP-06).
            if (in_array($status, Employee::BLOCKING_STATUSES, true)) {
                $revoked = $this->revokeQrCodes($employee);
            }

            // The audit key is "qr_codes_revoked" on purpose: a key containing the word "token" would be masked.
            AuditLogger::log('employee.status_changed', 'employee', $employee->id,
                ['employment_status' => $old],
                ['employment_status' => $status, 'qr_codes_revoked' => $revoked],
            );
        });
    }

    /** Marks every not-yet-revoked QR code of this employee as revoked. Returns how many. */
    public function revokeQrCodes(Employee $employee): int
    {
        return QrCode::query()
            ->where('employee_id', $employee->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    /**
     * The next free number, for example EMP-0001.
     * With $lock the matching rows are locked, so two people saving at once do not read the same highest number.
     */
    public function nextNumber(bool $lock = false): string
    {
        $query = Employee::query()->where('employee_number', 'like', self::PREFIX.'%');

        if ($lock) {
            $query->lockForUpdate();
        }

        $highest = $query->pluck('employee_number')
            ->map(fn (string $number) => (int) substr($number, strlen(self::PREFIX)))
            ->max() ?? 0;

        return self::PREFIX.str_pad((string) ($highest + 1), 4, '0', STR_PAD_LEFT);
    }

    /** @return array<string, mixed> */
    private function snapshot(Employee $employee): array
    {
        return [
            'employee_number' => $employee->employee_number,
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'phone' => $employee->phone,
            'hire_date' => $employee->hire_date?->format('Y-m-d'),
            'position_id' => (int) $employee->position_id,
            'user_id' => $employee->user_id === null ? null : (int) $employee->user_id,
            'employment_status' => $employee->employment_status,
        ];
    }
}