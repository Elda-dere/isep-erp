<?php

use App\Http\Controllers\Attendance\MyQrController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission:qr.own', 'throttle:30,1'])->group(function () {
    Route::get('/my-qr', [MyQrController::class, 'show'])->name('attendance.my-qr');
});