<?php

use App\Http\Controllers\Attendance\AttendanceSummaryController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/attendance/summary', [AttendanceSummaryController::class, 'index'])
        ->middleware('permission:attendance.view_all')
        ->name('attendance.summary');

    // Only people who may correct attendance can download everyone's data.
    Route::get('/attendance/summary/export', [AttendanceSummaryController::class, 'export'])
        ->middleware(['permission:attendance.correct', 'throttle:10,1'])
        ->name('attendance.summary.export');
});