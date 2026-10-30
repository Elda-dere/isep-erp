<?php

namespace App\Models;

use App\Support\IpRange;
use Illuminate\Database\Eloquent\Model;

class ApprovedNetwork extends Model
{
    // The approved_networks table has no created_at or updated_at columns.
    public $timestamps = false;

    protected $fillable = ['name', 'ip_range', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** Is this address inside any ACTIVE network? With no active network the answer is always no. */
    public static function allows(string $ip): bool
    {
        return static::query()
            ->where('is_active', true)
            ->get()
            ->contains(fn (self $network) => IpRange::contains($ip, $network->ip_range));
    }
}