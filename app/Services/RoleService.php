<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoleService
{
    /** This role can never be renamed or lose a permission, so nobody can lock themselves out. */
    public const LOCKED = 'Administrator';

    public function isLocked(Role $role): bool
    {
        return $role->name === self::LOCKED;
    }

    /** @param array<int, int|string> $permissionIds */
    public function create(string $name, array $permissionIds): Role
    {
        $ids = $this->ids($permissionIds);

        return DB::transaction(function () use ($name, $ids) {
            $role = Role::create(['name' => $name]);
            $role->permissions()->sync($ids);

            AuditLogger::log('role.created', 'role', $role->id, null, [
                'name' => $name,
                'permissions' => $this->permissionNames($ids),
            ]);

            return $role;
        });
    }

    /** @param array<int, int|string> $permissionIds */
    public function update(Role $role, string $name, array $permissionIds): Role
    {
        if ($this->isLocked($role)) {
            throw ValidationException::withMessages([
                'name' => 'The Administrator role is locked and cannot be changed.',
            ]);
        }

        $ids = $this->ids($permissionIds);

        return DB::transaction(function () use ($role, $name, $ids) {
            $oldIds = $this->ids($role->permissions()->pluck('permissions.id')->all());

            if ($role->name !== $name) {
                $oldName = $role->name;
                $role->update(['name' => $name]);

                AuditLogger::log('role.updated', 'role', $role->id, ['name' => $oldName], ['name' => $name]);
            }

            if ($oldIds !== $ids) {
                $role->permissions()->sync($ids);

                AuditLogger::log('role.permissions_changed', 'role', $role->id,
                    ['permissions' => $this->permissionNames($oldIds)],
                    ['permissions' => $this->permissionNames($ids)],
                );
            }

            return $role;
        });
    }

    /** @return array<int, int> */
    private function ids(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);

        return $ids;
    }

    /** @return array<int, string> */
    private function permissionNames(array $ids): array
    {
        return Permission::whereIn('id', $ids)->orderBy('name')->pluck('name')->all();
    }
}