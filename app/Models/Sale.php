<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    public const UPDATED_AT = null; // the sales table only has created_at

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
}
