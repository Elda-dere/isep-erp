<?php

namespace App\Http\Controllers\Users;

use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController
{
    public function __construct(private UserService $users) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
            'role' => ['nullable', 'integer'],
        ]);

        $users = User::query()
            ->with('roles:id,name')
            ->when($filters['search'] ?? null, function ($query, $value) {
                // % and _ are escaped so a person finds exactly what they typed.
                $like = '%'.addcslashes($value, '%_\\').'%';
                $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->when(($filters['status'] ?? null) === 'active', fn ($q) => $q->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($filters['role'] ?? null, fn ($q, $value) => $q->whereHas('roles', fn ($r) => $r->where('roles.id', $value)))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'roles' => $user->roles->pluck('name')->sort()->values()->all(),
            ]);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'status', 'role']),
            'roles' => $this->roles(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Users/Form', [
            'user' => null,
            'roles' => $this->roles(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->users->create($data, $data['roles'] ?? []);

        return redirect()->route('users.index')->with('success', 'User created.');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Users/Form', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'role_ids' => $user->roles()->pluck('roles.id')->all(),
            ],
            'roles' => $this->roles(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $this->users->update($user, $data, $data['roles'] ?? []);

        return redirect()->route('users.index')->with('success', 'User updated.');
    }

    public function activate(User $user): RedirectResponse
    {
        $this->users->activate($user);

        return back()->with('success', 'User activated.');
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        $this->users->deactivate($user, $request->user());

        return back()->with('success', 'User deactivated.');
    }

    public function password(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);

        $this->users->resetPassword($user, $validated['password']);

        return back()->with('success', 'Password updated.');
    }

    private function roles()
    {
        return Role::query()->orderBy('name')->get(['id', 'name']);
    }
}