<?php

use App\Http\Controllers\Catalogue\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:products.view')->group(function () {
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
});

Route::middleware('permission:products.manage')->group(function () {
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::patch('/products/{product}/deactivate', [ProductController::class, 'deactivate'])->name('products.deactivate');
    Route::patch('/products/{product}/activate', [ProductController::class, 'activate'])->name('products.activate');
});
