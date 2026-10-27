<?php

namespace App\Models;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    public $timestamps = false; // the table has no created_at/updated_at

    protected $fillable = ['purchase_order_id', 'product_id', 'quantity', 'unit_cost'];

    // Casts keep quantities and money as exact decimal strings, never floats.
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // quantity x unit cost, rounded half-up to 2 decimals (DR-03).
    // Example: 0.010 x 0.50 = 0.005, which rounds up to "0.01".
    public function lineTotal(): string
    {
        return (string) BigDecimal::of($this->quantity)
            ->multipliedBy($this->unit_cost)
            ->toScale(2, RoundingMode::HalfUp);
    }
}
