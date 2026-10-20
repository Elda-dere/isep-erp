<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            'kilogram' => 'kg',
            'gram' => 'g',
            'litre' => 'l',
            'millilitre' => 'ml',
            'metre' => 'm',
            'piece' => 'pcs',
            'box' => 'box',
            'dozen' => 'doz',
        ];

        foreach ($units as $name => $symbol) {
            DB::table('units')->updateOrInsert(['name' => $name], ['symbol' => $symbol]);
        }
    }
}