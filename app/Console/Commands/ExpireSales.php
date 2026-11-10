<?php

namespace App\Console\Commands;

use App\Services\SaleService;
use Illuminate\Console\Command;

class ExpireSales extends Command
{
    protected $signature = 'sales:expire';

    protected $description = 'Cancel pending sales that were not paid in time and give their stock back (SAL-08)';

    // The scheduler runs this every 5 minutes (routes/console.php). The rules live in SaleService.
    public function handle(SaleService $sales): int
    {
        $count = $sales->expireStale();

        $this->info("Expired {$count} unpaid sale(s).");

        return self::SUCCESS;
    }
}
