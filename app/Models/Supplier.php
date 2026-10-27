<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    public $timestamps = false; // the suppliers table has no created_at/updated_at

    // Columns that may be filled in bulk (mass-assignment guard).
    protected $fillable = ['name', 'phone', 'email', 'address'];

    // A supplier can have many purchase orders.
    // PRC-01: a supplier that has purchase orders cannot be deleted.
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
