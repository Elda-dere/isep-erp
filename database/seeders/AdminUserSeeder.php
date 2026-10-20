<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('erp.admin.email');
        $password = config('erp.admin.password');

        if (blank($email) || blank($password)) {
            throw new RuntimeException('Set ADMIN_EMAIL and ADMIN_PASSWORD in .env before seeding, then run: php artisan config:clear');
        }

        if (strlen($password) < 10) {
            throw new RuntimeException('ADMIN_PASSWORD must be at least 10 characters (NFR-SEC-04).');
        }

        // Created only if no user with this email exists, so re-running never overwrites a changed password.
        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => config('erp.admin.name'),
                'password' => $password, // hashed automatically by the User model cast
                'is_active' => true,
            ]
        );

        $admin->roles()->syncWithoutDetaching([Role::where('name', 'Administrator')->firstOrFail()->id]);
    }
}