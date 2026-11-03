<?php

return [

    /*
    | How long a QR code on an employee's phone stays valid, in seconds (SRS default: 60).
    | Kept between 10 and 300 by QrTokenService. Read from .env, never from the database.
    */
    'qr_lifetime_seconds' => (int) env('QR_LIFETIME_SECONDS', 60),

    /*
    | Minimum time between a check-in and the check-out, in seconds (SRS default: 1 minute).
    | It stops an accidental double scan from checking someone out at once (ATT-05).
    */
    'minimum_gap_seconds' => (int) env('ATTENDANCE_MIN_GAP_SECONDS', 60),

];