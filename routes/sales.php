<?php

use App\Http\Controllers\Sales\CustomerController;
use Illuminate\Support\Facades\Route;

// There is only one customer permission (customers.manage), so it guards the whole screen,
// the list included. The form pages are here too: a user who cannot save should not open the form.
// The sales routes will be added to this file in the next slices.
Route::middleware('permission:customers.manage')->group(function () {
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
});
