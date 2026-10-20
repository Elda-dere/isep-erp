<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        // Used for anonymous sales so sales.customer_id is never null (SAL-01).
        DB::table('customers')->updateOrInsert(['name' => 'Walk-in customer'], []);
    }
}