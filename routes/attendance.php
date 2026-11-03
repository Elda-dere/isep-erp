<?php

use App\Http\Controllers\Attendance\AttendanceBoardController;
use App\Http\Controllers\Attendance\MyAttendanceController;
use App\Http\Controllers\Attendance\MyQrController;
use App\Http\Controllers\Attendance\ScannerController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::middleware(['permission:qr.own', 'throttle:30,1'])->group(function () {
        Route::get('/my-qr', [MyQrController::class, 'show'])->name('attendance.my-qr');
    });

    Route::middleware('permission:attendance.scan')->group(function () {
        Route::get('/scanner', [ScannerController::class, 'show'])->name('attendance.scanner');
        Route::post('/attendance/scan', [ScannerController::class, 'store'])
            ->middleware('throttle:120,1')
            ->name('attendance.scan');
    });

    Route::get('/my-attendance', [MyAttendanceController::class, 'index'])
        ->middleware('permission:attendance.view_own')
        ->name('attendance.mine');

    Route::get('/attendance/board', [AttendanceBoardController::class, 'index'])
        ->middleware('permission:attendance.view_all')
        ->name('attendance.board');
});