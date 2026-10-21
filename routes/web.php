<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return view('welcome');
});

// Temporary page that proves Laravel -> Inertia -> Vue works. Removed after the login task.
Route::get('/hello', function () {
    return Inertia::render('Hello', [
        'message' => 'This text came from Laravel and was drawn by Vue.',
    ]);
});

// Each module keeps its own route file, with one line here (agreed with Developer B).
require __DIR__.'/catalogue.php';
require __DIR__.'/auth.php';
