<?php

return [

    /*
    | How long a QR code on an employee's phone stays valid, in seconds (SRS default: 60).
    | Kept between 10 and 300 by QrTokenService. Read from .env, never from the database.
    | Later attendance tasks add more settings here (minimum gap, start time, retention).
    */
    'qr_lifetime_seconds' => (int) env('QR_LIFETIME_SECONDS', 60),

];