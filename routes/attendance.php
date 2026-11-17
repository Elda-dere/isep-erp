<?php

use App\Http\Controllers\Attendance\AttendanceBoardController;
use App\Http\Controllers\Attendance\AttendanceModesController;
use App\Http\Controllers\Attendance\CheckInController;
use App\Http\Controllers\Attendance\MyAttendanceController;
use App\Http\Controllers\Attendance\MyQrController;
use App\Http\Controllers\Attendance\ScannerController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Mobile Check-In when scanned directly with a smartphone camera
    Route::get('/attendance/check-in', [CheckInController::class, 'handle'])->name('attendance.check-in');
    Route::get('/attendance/static-qr', [AttendanceModesController::class, 'staticQrView'])->name('attendance.static-qr');

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

    // Attendance Capture Modes & Settings (matching reference design)
    Route::middleware('permission:attendance.view_all')->group(function () {
        Route::get('/attendance/modes', [AttendanceModesController::class, 'index'])->name('attendance.modes');
        Route::post('/attendance/modes/settings', [AttendanceModesController::class, 'saveSettings'])->name('attendance.modes.settings');
        Route::post('/attendance/modes/static-token', [AttendanceModesController::class, 'generateStaticToken'])->name('attendance.modes.static-token');
        Route::post('/attendance/modes/toggle', [AttendanceModesController::class, 'toggleMode'])->name('attendance.modes.toggle');
        Route::post('/attendance/modes/branches', [AttendanceModesController::class, 'storeBranch'])->name('attendance.modes.branches.store');
        Route::delete('/attendance/modes/branches/{branch}', [AttendanceModesController::class, 'destroyBranch'])->name('attendance.modes.branches.destroy');
        Route::post('/attendance/modes/devices', [AttendanceModesController::class, 'storeDevice'])->name('attendance.modes.devices.store');
        Route::delete('/attendance/modes/devices/{device}', [AttendanceModesController::class, 'destroyDevice'])->name('attendance.modes.devices.destroy');
        Route::post('/attendance/modes/devices/{device}/sync', [AttendanceModesController::class, 'syncDevice'])->name('attendance.modes.devices.sync');
    });
});