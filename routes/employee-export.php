<?php

use App\Http\Controllers\Employees\EmployeeExportController;
use Illuminate\Support\Facades\Route;

// Only people who may change employees can download all of their personal data.
Route::middleware(['auth', 'permission:employees.manage', 'throttle:10,1'])->group(function () {
    Route::get('/employees/export', EmployeeExportController::class)->name('employees.export');
});