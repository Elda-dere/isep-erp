<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The schema has no remember_token column (SRS section 9),
     * so "remember me" is switched off.
     */
    public function getRememberTokenName(): string
    {
        return '';
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('name', $permission))
            ->exists();
    }
}