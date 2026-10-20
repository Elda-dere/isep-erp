<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Before Laravel answers any can('...') question, ask our permission tables.
        // true means allowed. null means "no opinion", so other rules can still decide.
        Gate::before(fn (User $user, string $ability) => $user->hasPermission($ability) ?: null);
    }
}