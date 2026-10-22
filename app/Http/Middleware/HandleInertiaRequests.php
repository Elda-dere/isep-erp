<?php

namespace App\Http\Middleware;

use App\Models\Permission;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Data every page receives.
     *
     * The permission list only decides which menu items to SHOW.
     * The server still checks every request (NFR-SEC-03).
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => fn () => $this->authData($request),
        ];
    }

    /** @return array{user: array<string, mixed>|null, permissions: array<int, string>} */
    private function authData(Request $request): array
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            return ['user' => null, 'permissions' => []];
        }

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'permissions' => Permission::query()
                ->whereHas('roles.users', fn ($query) => $query->where('users.id', $user->id))
                ->orderBy('name')
                ->pluck('name')
                ->all(),
        ];
    }
}