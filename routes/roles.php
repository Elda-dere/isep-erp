<?php

use App\Http\Controllers\Roles\RoleController;
use Illuminate\Support\Facades\Route;

// There is no delete route on purpose: roles are renamed or emptied, never removed.
Route::middleware(['auth', 'permission:roles.manage'])->group(function () {
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
});