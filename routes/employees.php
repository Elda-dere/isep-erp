<?php

use App\Http\Controllers\Employees\EmployeeController;
use Illuminate\Support\Facades\Route;

// There is no delete route on purpose: employees are never deleted (EMP-07).
Route::middleware('auth')->group(function () {
    // Either permission opens the list. Only employees.manage may change anything.
    Route::get('/employees', [EmployeeController::class, 'index'])
        ->middleware('permission:employees.view,employees.manage')
        ->name('employees.index');

    Route::middleware('permission:employees.manage')->group(function () {
        Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create');
        Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
        Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
        Route::patch('/employees/{employee}/status', [EmployeeController::class, 'status'])->name('employees.status');
    });
});