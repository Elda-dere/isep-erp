<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    // Columns that may be filled in bulk (mass-assignment guard).
    protected $fillable = [
        'category_id',
        'unit_id',
        'sku',
        'name',
        'description',
        'cost_price',
        'selling_price',
        'reorder_level',
        'is_active',
    ];

    // Casts keep money and quantities as exact decimal strings, never floats.
    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'reorder_level' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    // Each product belongs to one category and one unit of measure.
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    // One product can have a stock row in each warehouse.
    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }

    // A "scope" is a reusable query piece: Product::lowStock()->get() or ->count().
    // The method name MUST start with "scope"; Laravel exposes it as lowStock().
    // Rule INV-09: total quantity over all warehouses is at or below the reorder level.
    // Only active products count: a discontinued product at 0 stock is not a problem.
    public function scopeLowStock(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereRaw(
                'COALESCE((SELECT SUM(quantity) FROM inventory_stocks
                           WHERE inventory_stocks.product_id = products.id), 0)
                 <= products.reorder_level'
            );
    }
}
