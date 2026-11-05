<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    public $timestamps = false; // the customers table has no created_at/updated_at

    // The seeded record that anonymous sales use (SRS SAL-01), so a sale never has a missing customer.
    public const WALK_IN_NAME = 'Walk-in customer';

    // Columns that may be filled in bulk (mass-assignment guard).
    protected $fillable = ['name', 'phone', 'email', 'address'];

    // A customer can have many sales. A customer with sales cannot be deleted.
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    // Compared without caring about capital letters or spaces around the name.
    public static function isWalkInName(string $name): bool
    {
        return mb_strtolower(trim($name)) === mb_strtolower(self::WALK_IN_NAME);
    }

    public function isWalkIn(): bool
    {
        return self::isWalkInName((string) $this->name);
    }
}
