<?php

return [
    // PAY-17: keys live ONLY in the .env file. Never write them here, in Git, in logs, or in Vue code.
    'secret_key' => env('CHAPA_SECRET_KEY'),
    'public_key' => env('CHAPA_PUBLIC_KEY'),
    'webhook_secret' => env('CHAPA_WEBHOOK_SECRET'),

    // Check developer.chapa.co before changing the base address.
    'base_url' => rtrim((string) env('CHAPA_BASE_URL', 'https://api.chapa.co'), '/'),

    // "test" for test keys, "live" for live keys. A verified payment whose mode differs is NOT accepted.
    'mode' => env('CHAPA_MODE', 'test'),

    'timeout_seconds' => 15,
    'reconcile_after_minutes' => 10, // PAY-13: payments still pending after this long are verified by the scheduler
    'retry_delays_ms' => [500, 1500], // retried at most twice, with back-off (SRS 6.2)

    // The webhook itself is set in the Chapa dashboard. A callback is only sent if you configure one.
    'callback_url' => env('CHAPA_CALLBACK_URL'),
    // Where the customer lands after paying. {tx_ref} is replaced by the payment's reference.
    'return_url' => env('CHAPA_RETURN_URL', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/pay/{tx_ref}/return'),

    'placeholder_email' => env('CHAPA_PLACEHOLDER_EMAIL'),
    'checkout_title' => env('CHAPA_CHECKOUT_TITLE', 'ERP payment'),
];