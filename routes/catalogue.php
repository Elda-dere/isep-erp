<?php

use App\Http\Controllers\Catalogue\ProductController;
use Illuminate\Support\Facades\Route;

// Reading needs products.view.
Route::middleware('permission:products.view')->group(function () {
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
});

// Creating, editing and (de)activating need products.manage.
// The form pages are here too: a user who cannot save should not open the form.
Route::middleware('permission:products.manage')->group(function () {
    Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::patch('/products/{product}/deactivate', [ProductController::class, 'deactivate'])->name('products.deactivate');
    Route::patch('/products/{product}/activate', [ProductController::class, 'activate'])->name('products.activate');
});
