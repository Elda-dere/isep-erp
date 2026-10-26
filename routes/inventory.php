<?php

use App\Http\Controllers\Inventory\StockController;
use App\Http\Controllers\Inventory\StockReportController;
use App\Http\Controllers\Inventory\WarehouseController;
use Illuminate\Support\Facades\Route;

// Reading needs inventory.view.
Route::middleware('permission:inventory.view')->group(function () {
    Route::get('/warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');

    Route::get('/stock', [StockReportController::class, 'overview'])->name('stock.overview');
    Route::get('/stock/low', [StockReportController::class, 'lowStock'])->name('stock.low');
    Route::get('/stock/card/{product}', [StockReportController::class, 'card'])->name('stock.card');
});

// Creating and editing warehouses needs warehouses.manage.
// The form pages are in this group too: a user who cannot save should not open the form.
Route::middleware('permission:warehouses.manage')->group(function () {
    Route::get('/warehouses/create', [WarehouseController::class, 'create'])->name('warehouses.create');
    Route::post('/warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
    Route::get('/warehouses/{warehouse}/edit', [WarehouseController::class, 'edit'])->name('warehouses.edit');
    Route::put('/warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('warehouses.update');
});

// Changing stock (opening stock, adjustments) needs inventory.adjust.
Route::middleware('permission:inventory.adjust')->group(function () {
    Route::post('/stock/opening', [StockController::class, 'storeOpening'])->name('stock.opening');
    Route::post('/stock/adjustments', [StockController::class, 'storeAdjustment'])->name('stock.adjust');
});
