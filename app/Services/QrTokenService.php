<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\QrCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class QrTokenService
{
    /**
     * Makes a new single-use token for this employee and cancels their earlier unexpired ones (ATT-01).
     * Only the SHA-256 fingerprint is stored. The token itself exists only in the returned array.
     *
     * @return array{token: string, expires_at: Carbon, expires_in: int}
     */
    public function issue(Employee $employee): array
    {
        $lifetime = $this->lifetime();

        // 32 random bytes written as 64 characters (256 bits).
        $token = bin2hex(random_bytes(32));

        $expiresAt = DB::transaction(function () use ($employee, $token, $lifetime) {
            // Lock the employee row, so two tabs or phones cannot issue at the very same moment.
            Employee::query()->whereKey($employee->id)->lockForUpdate()->first();

            $now = now();

            QrCode::query()
                ->where('employee_id', $employee->id)
                ->whereNull('revoked_at')
                ->where('expires_at', '>', $now)
                ->update(['revoked_at' => $now]);

            $expiresAt = $now->copy()->addSeconds($lifetime);

            QrCode::create([
                'employee_id' => $employee->id,
                'token_hash' => self::hash($token),
                'expires_at' => $expiresAt,
                // The table has a database default, but this is set in PHP so it is always UTC.
                'created_at' => $now,
            ]);

            return $expiresAt;
        });

        return ['token' => $token, 'expires_at' => $expiresAt, 'expires_in' => $lifetime];
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function lifetime(): int
    {
        return max(10, min(300, (int) config('attendance.qr_lifetime_seconds', 60)));
    }
}