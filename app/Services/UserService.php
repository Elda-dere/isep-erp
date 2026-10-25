<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserService
{
    public const ADMIN_ROLE = 'Administrator';

    /** @param array<int, int|string> $roleIds */
    public function create(array $data, array $roleIds): User
    {
        $roleIds = $this->ids($roleIds);

        return DB::transaction(function () use ($data, $roleIds) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'], // hashed by the model cast
                'is_active' => $data['is_active'] ?? true,
            ]);

            $user->roles()->sync($roleIds);

            // The password is never part of the audit values.
            AuditLogger::log('user.created', 'user', $user->id, null, [
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'roles' => $this->roleNames($roleIds),
            ]);

            return $user;
        });
    }

    /** @param array<int, int|string> $roleIds */
    public function update(User $user, array $data, array $roleIds): User
    {
        $roleIds = $this->ids($roleIds);

        return DB::transaction(function () use ($user, $data, $roleIds) {
            $oldRoleIds = $this->ids($user->roles()->pluck('roles.id')->all());
            $adminId = Role::where('name', self::ADMIN_ROLE)->value('id');

            // Removing the Administrator role from the last active Administrator is refused.
            if ($adminId && in_array($adminId, $oldRoleIds, true) && ! in_array($adminId, $roleIds, true)) {
                $this->assertNotLastActiveAdmin($user, 'roles', 'The last active Administrator must keep the Administrator role.');
            }

            $old = [];
            $new = [];
            foreach (['name', 'email'] as $field) {
                if ($user->{$field} !== $data[$field]) {
                    $old[$field] = $user->{$field};
                    $new[$field] = $data[$field];
                }
            }

            if ($new !== []) {
                $user->update($new);
                AuditLogger::log('user.updated', 'user', $user->id, $old, $new);
            }

            if ($oldRoleIds !== $roleIds) {
                $user->roles()->sync($roleIds);
                AuditLogger::log('user.roles_changed', 'user', $user->id,
                    ['roles' => $this->roleNames($oldRoleIds)],
                    ['roles' => $this->roleNames($roleIds)],
                );
            }

            return $user;
        });
    }

    public function deactivate(User $user, User $actor): void
    {
        DB::transaction(function () use ($user, $actor) {
            if ($user->is($actor)) {
                throw ValidationException::withMessages(['user' => 'You cannot deactivate your own account.']);
            }

            if (! $user->is_active) {
                return;
            }

            if ($user->roles()->where('name', self::ADMIN_ROLE)->exists()) {
                $this->assertNotLastActiveAdmin($user, 'user', 'The last active Administrator cannot be deactivated.');
            }

            $user->update(['is_active' => false]);

            AuditLogger::log('user.deactivated', 'user', $user->id, ['is_active' => true], ['is_active' => false]);
        });
    }

    public function activate(User $user): void
    {
        DB::transaction(function () use ($user) {
            if ($user->is_active) {
                return;
            }

            $user->update(['is_active' => true]);

            AuditLogger::log('user.activated', 'user', $user->id, ['is_active' => false], ['is_active' => true]);
        });
    }

    public function resetPassword(User $user, string $password): void
    {
        DB::transaction(function () use ($user, $password) {
            $user->update(['password' => $password]);

            // Only the fact is logged, never the password.
            AuditLogger::log('user.password_reset', 'user', $user->id);
        });
    }

    /**
     * Lock the active Administrators while counting, so two people deactivating
     * two Administrators at the same moment cannot leave the system with none.
     */
    private function assertNotLastActiveAdmin(User $user, string $field, string $message): void
    {
        if (! $user->is_active) {
            return;
        }

        $activeAdmins = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->where('name', self::ADMIN_ROLE))
            ->lockForUpdate()
            ->pluck('users.id');

        if ($activeAdmins->count() <= 1) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    /** @return array<int, int> */
    private function ids(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);

        return $ids;
    }

    /** @return array<int, string> */
    private function roleNames(array $ids): array
    {
        return Role::whereIn('id', $ids)->orderBy('name')->pluck('name')->all();
    }
}