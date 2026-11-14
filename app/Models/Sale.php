<?php

namespace App\Models;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    public const UPDATED_AT = null; // the sales table only has created_at

    // ETB is the default currency (SRS section 2.4).
    public const DEFAULT_CURRENCY = 'ETB';

    // The only allowed statuses (SRS section 5.3). Code uses these names, never typed text.
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = ['sale_number', 'customer_id', 'status', 'total_amount', 'currency', 'created_by'];

    // The cast keeps money as an exact decimal string, never a float.
    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    // created_by points to users.id (SRS assumption A-02).
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Lines keep the order in which they were entered.
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class)->orderBy('id');
    }
        // A sale can have several payment attempts (a retry creates a new one).
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // DR-05: the sum of the line totals, as an exact decimal string like "25.01".
    // sales.total_amount must always equal this. Tests (and a future integrity report) compare the two.
    public function linesTotal(): string
    {
        $sum = BigDecimal::zero()->toScale(2);

        foreach ($this->items as $item) {
            $sum = $sum->plus($item->line_total);
        }

        return (string) $sum;
    }
}
