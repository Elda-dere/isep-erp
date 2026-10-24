<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        // is_active = true is part of the lookup, so a deactivated user can never sign in (AUTH-02).
        // "Remember me" is not used: the schema has no remember_token column.
        $credentials = [
            'email' => $this->string('email')->toString(),
            'password' => $this->string('password')->toString(),
            'is_active' => true,
        ];

        if (! Auth::attempt($credentials)) {
            RateLimiter::hit($this->throttleKey());

            $this->logFailure($credentials['email']);

            // One message for every failure, so the page never reveals which part was wrong.
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /** The audit log may say why a login failed. The page never does (AUTH-07). */
    private function logFailure(string $email): void
    {
        $user = User::where('email', $email)->first();

        $reason = ! $user ? 'unknown_email' : (! $user->is_active ? 'inactive_account' : 'wrong_password');

        // The typed password is never stored.
        AuditLogger::log('auth.login_failed', $user ? 'user' : null, $user?->id, null, [
            'email' => $email,
            'reason' => $reason,
        ]);
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "Too many login attempts. Try again in {$seconds} seconds.",
        ]);
    }

    /** One counter per email AND IP address (AUTH-07). */
    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')->toString()).'|'.$this->ip());
    }
}