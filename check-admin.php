<?php

// TEMPORARY diagnostic. Never commit this file. Delete it when you are done.
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$reset = in_array('reset', $argv, true);

$email = config('erp.admin.email');
$password = config('erp.admin.password');

echo 'Database in use : ', DB::connection()->getDatabaseName(), PHP_EOL;
echo 'Users in table  : ', User::count(), PHP_EOL;
echo 'ADMIN_EMAIL     : ', ($email ?: '(NOT SET in .env)'), PHP_EOL;
echo 'ADMIN_PASSWORD  : ', ($password ? 'set, '.strlen($password).' characters' : '(NOT SET in .env)'), PHP_EOL;

$user = $email ? User::where('email', $email)->first() : null;

if (! $user) {
    echo 'Admin user      : NOT FOUND', PHP_EOL;
    exit(1);
}

echo 'Admin user      : found (id '.$user->id.')', PHP_EOL;
echo 'Active          : ', ($user->is_active ? 'yes' : 'NO'), PHP_EOL;
echo 'Roles           : ', ($user->roles()->pluck('name')->implode(', ') ?: '(none)'), PHP_EOL;
echo 'Password in .env matches the database: ', (($password && Hash::check($password, $user->password)) ? 'YES' : 'NO'), PHP_EOL;

if ($reset) {
    if (blank($password) || strlen($password) < 10) {
        echo 'Cannot reset: ADMIN_PASSWORD must be set and at least 10 characters.', PHP_EOL;
        exit(1);
    }

    $user->password = $password; // hashed by the model cast
    $user->is_active = true;
    $user->save();

    $role = Role::where('name', 'Administrator')->first();
    if ($role) {
        $user->roles()->syncWithoutDetaching([$role->id]);
    }

    echo 'RESET DONE: the password from .env is saved and the account is active.', PHP_EOL;
}