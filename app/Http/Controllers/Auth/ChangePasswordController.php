<?php

namespace App\Http\Controllers\Auth;

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

        // TODO(audit-log task): record "user.password_changed" (AUD-02).

        return back();
    }
}