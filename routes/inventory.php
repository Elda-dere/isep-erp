<?php

use App\Http\Controllers\Inventory\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:inventory.view')->group(function () {
    Route::get('/warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
});

Route::middleware('permission:warehouses.manage')->group(function () {
    Route::post('/warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
    Route::put('/warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('warehouses.update');
});
