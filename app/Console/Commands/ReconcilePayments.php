<?php

namespace App\Console\Commands;

use App\Services\PaymentService;
use Illuminate\Console\Command;

class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile';

    protected $description = 'Verify old pending Chapa payments and expire those of cancelled sales (PAY-13)';

    // The scheduler runs this every 5 minutes (routes/console.php). The rules live in PaymentService.
    public function handle(PaymentService $payments): int
    {
        $result = $payments->reconcile();

        $this->info("Checked {$result['checked']} pending payment(s), expired {$result['expired']}.");

        return self::SUCCESS;
    }
}