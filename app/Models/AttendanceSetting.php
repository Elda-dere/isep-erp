<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceSetting extends Model
{
    protected $fillable = [
        'salary_calculation_method',
        'static_qr_enabled',
        'static_qr_token',
        'dynamic_qr_enabled',
    ];

    protected function casts(): array
    {
        return [
            'static_qr_enabled' => 'boolean',
            'dynamic_qr_enabled' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::firstOrCreate(
            ['id' => 1],
            [
                'salary_calculation_method' => 'Fixed 30 Days Month',
                'static_qr_enabled' => true,
                'static_qr_token' => bin2hex(random_bytes(32)),
                'dynamic_qr_enabled' => true,
            ]
        );
    }
}
