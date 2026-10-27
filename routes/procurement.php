<?php

use App\Http\Controllers\Procurement\SupplierController;
use Illuminate\Support\Facades\Route;

// There is only one supplier permission (suppliers.manage), so it guards the whole screen,
// the list included. The form pages are here too: a user who cannot save should not open the form.
Route::middleware('permission:suppliers.manage')->group(function () {
    Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::get('/suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create');
    Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
    Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit');
    Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
    Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
});
