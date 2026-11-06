<?php

namespace App\Console\Commands;

use App\Models\QrCode;
use App\Services\AuditLogger;
use Illuminate\Console\Command;

class PurgeQrCodes extends Command
{
    protected $signature = 'attendance:purge-qr-codes {--days= : Keep used and expired codes this many days (default: the QR_RETENTION_DAYS setting)}';

    protected $description = 'Delete used and expired QR codes older than the retention period (ATT-15)';

    public function handle(): int
    {
        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) config('attendance.qr_retention_days', 30);

        // Never less than one day, so a code that was just used is not deleted by mistake.
        $days = max(1, $days);
        $cutoff = now()->subDays($days);

        $deleted = 0;

        // In batches, so a large table never has to be deleted in one go.
        do {
            $batch = QrCode::query()
                ->where(fn ($query) => $query->where('revoked_at', '<', $cutoff)->orWhere('expires_at', '<', $cutoff))
                ->limit(1000)
                ->delete();

            $deleted += $batch;
        } while ($batch === 1000);

        // A scheduled job has no signed-in user, so the audit row has none either (AUD-03).
        if ($deleted > 0) {
            AuditLogger::log('attendance.qr_codes_purged', null, null, null, [
                'qr_codes_deleted' => $deleted,
                'older_than_days' => $days,
            ]);
        }

        $this->info("Deleted {$deleted} QR code(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}