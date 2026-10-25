<?php

return [

    /*
    | The first administrator, created by AdminUserSeeder.
    | Values come from .env so no password is ever stored in code.
    */
    'admin' => [
        'name' => env('ADMIN_NAME', 'System Administrator'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    /*
    | Times are stored in UTC and shown in this business time zone (DR-07, NFR-LOC-02).
    */
    'timezone' => env('ERP_TIMEZONE', 'Africa/Addis_Ababa'),

];