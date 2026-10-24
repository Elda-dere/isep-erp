<?php

namespace App\Http\Controllers\Auth;

use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChangePasswordController
{
    public function edit(): Response
    {
        return Inertia::render('Account/Password');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:10', 'confirmed', 'different:current_password'],
        ]);

        // The User model hashes the password automatically (the "hashed" cast).
        $request->user()->update(['password' => $validated['password']]);

        // Only the fact is logged, never any password.
        AuditLogger::log('user.password_changed', 'user', $request->user()->id);

        return back();
    }
}