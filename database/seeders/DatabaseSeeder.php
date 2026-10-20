<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Order matters: roles need permissions, and the admin needs roles.
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            UnitSeeder::class,
            CustomerSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}