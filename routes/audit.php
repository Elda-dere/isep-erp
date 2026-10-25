<?php

use App\Http\Controllers\Audit\AuditLogController;
use Illuminate\Support\Facades\Route;

// Read-only on purpose: there is no route to edit or delete an audit row (AUD-04).
Route::middleware(['auth', 'permission:audit.view'])->group(function () {
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit.index');
});