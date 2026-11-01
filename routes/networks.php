<?php

use App\Http\Controllers\Networks\NetworkController;
use Illuminate\Support\Facades\Route;

// There is no delete route on purpose: a network is deactivated, never removed.
Route::middleware(['auth', 'permission:networks.manage'])->group(function () {
    Route::get('/networks', [NetworkController::class, 'index'])->name('networks.index');
    Route::post('/networks', [NetworkController::class, 'store'])->name('networks.store');
    Route::put('/networks/{network}', [NetworkController::class, 'update'])->name('networks.update');
    Route::patch('/networks/{network}/activate', [NetworkController::class, 'activate'])->name('networks.activate');
    Route::patch('/networks/{network}/deactivate', [NetworkController::class, 'deactivate'])->name('networks.deactivate');
});