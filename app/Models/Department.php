<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    // The departments table has no created_at or updated_at columns.
    public $timestamps = false;

    protected $fillable = ['name', 'description'];

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }
}