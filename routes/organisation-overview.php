<?php

use App\Http\Controllers\Organisation\OverviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission:employees.view'])->group(function () {
    Route::get('/organisation', [OverviewController::class, 'index'])->name('organisation.overview');
});