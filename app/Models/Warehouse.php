<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'location'];

    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }
}
