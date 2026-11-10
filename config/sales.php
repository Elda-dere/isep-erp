<?php

return [
    // SAL-08: a sale that is still pending (unpaid) after this many minutes is cancelled
    // automatically by the "sales:expire" job, and its stock goes back.
    // To change it on a server, set SALE_PENDING_EXPIRY_MINUTES in the .env file.
    'pending_expiry_minutes' => (int) env('SALE_PENDING_EXPIRY_MINUTES', 30),
];
