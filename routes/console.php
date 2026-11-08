<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Delete used and expired QR codes older than the retention period, every night (ATT-15).
\Illuminate\Support\Facades\Schedule::command('attendance:purge-qr-codes')
    ->dailyAt('02:00')
    ->timezone(config('erp.timezone'))
    ->withoutOverlapping();
