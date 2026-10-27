<?php

namespace App\Models;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    public const UPDATED_AT = null; // the table only has created_at

    // The only allowed statuses (SRS section 5.3). Code uses these names, never typed text.
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ORDERED = 'ordered';
    public const STATUS_PARTIALLY_RECEIVED = 'partially_received';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = ['supplier_id', 'order_number', 'status', 'ordered_at', 'created_by'];

    protected function casts(): array
    {
        return [
            'ordered_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    // created_by points to users.id (SRS assumption A-02).
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Lines keep the order in which they were entered.
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('id');
    }

    // PRC-04: the total is calculated from the lines and never stored.
    // It is the sum of each line's rounded total, as an exact decimal string like "25.01".
    public function total(): string
    {
        $sum = BigDecimal::zero()->toScale(2);

        foreach ($this->items as $item) {
            $sum = $sum->plus($item->lineTotal());
        }

        return (string) $sum;
    }
}
