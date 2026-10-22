<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => redirect()->route('dashboard'));

// Temporary page that proves Laravel -> Inertia -> Vue works. Removed in a later cleanup.
Route::get('/hello', function () {
    return Inertia::render('Hello', [
        'message' => 'This text came from Laravel and was drawn by Vue.',
    ]);
});

// Each module keeps its own route file, with one line here (agreed with Developer B).
feature/app-layout
require __DIR__ . '/catalogue.php';
require __DIR__ . '/inventory.php';
require __DIR__ . '/auth.php';
 main
