<?php

namespace App\Http\Controllers\Roles;

use App\Http\Requests\Roles\RoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RoleController
{
    public function __construct(private RoleService $roles) {}

    public function index(): Response
    {
        return Inertia::render('Roles/Index', [
            'roles' => Role::query()
                ->withCount(['users', 'permissions'])
                ->orderBy('name')
                ->get()
                ->map(fn (Role $role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'users_count' => $role->users_count,
                    'permissions_count' => $role->permissions_count,
                    'locked' => $this->roles->isLocked($role),
                ])
                ->all(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Roles/Form', [
            'role' => null,
            'groups' => $this->groups(),
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $this->roles->create($request->validated('name'), $request->validated('permissions') ?? []);

        return redirect()->route('roles.index')->with('success', 'Role created.');
    }

    public function edit(Role $role): Response
    {
        return Inertia::render('Roles/Form', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'locked' => $this->roles->isLocked($role),
                'permission_ids' => $role->permissions()->pluck('permissions.id')->all(),
            ],
            'groups' => $this->groups(),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $this->roles->update($role, $request->validated('name'), $request->validated('permissions') ?? []);

        return redirect()->route('roles.index')->with('success', 'Role updated.');
    }

    /** Permissions grouped by the text before the dot, for example "employees". */
    private function groups(): array
    {
        return Permission::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->groupBy(fn (Permission $permission) => Str::before($permission->name, '.'))
            ->map(fn ($items, $group) => [
                'group' => $group,
                'permissions' => $items->map(fn (Permission $p) => ['id' => $p->id, 'name' => $p->name])->values()->all(),
            ])
            ->values()
            ->all();
    }
}