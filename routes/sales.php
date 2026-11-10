<?php

use App\Http\Controllers\Sales\CustomerController;
use App\Http\Controllers\Sales\SaleController;
use Illuminate\Support\Facades\Route;

// There is only one customer permission (customers.manage), so it guards the whole screen,
// the list included. The form pages are here too: a user who cannot save should not open the form.
Route::middleware('permission:customers.manage')->group(function () {
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
});

// Making a sale needs sales.create. The screen is here too: a user who cannot save should not open it.
Route::middleware('permission:sales.create')->group(function () {
    Route::get('/sales/create', [SaleController::class, 'create'])->name('sales.create');
    Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
});

// Looking at sales needs sales.view. whereNumber() makes sure "create" is never read as a sale id.
Route::middleware('permission:sales.view')->group(function () {
    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
    Route::get('/sales/{sale}', [SaleController::class, 'show'])
        ->whereNumber('sale')
        ->name('sales.show');
});

// Cancelling needs its own permission, sales.cancel.
Route::middleware('permission:sales.cancel')->group(function () {
    Route::post('/sales/{sale}/cancel', [SaleController::class, 'cancel'])
        ->whereNumber('sale')
        ->name('sales.cancel');
});
