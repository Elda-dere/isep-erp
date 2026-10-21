<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
require __DIR__ . '/catalogue.php';
require __DIR__ . '/inventory.php';
