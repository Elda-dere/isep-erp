<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceipt extends Model
{
    public $timestamps = false; // the goods_receipts table has no created_at/updated_at

    // The only allowed statuses (SRS section 5.3).
    public const STATUS_DRAFT = 'draft';
    public const STATUS_POSTED = 'posted';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = ['purchase_order_id', 'receipt_number', 'received_by', 'received_at', 'status'];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    // received_by points to users.id (SRS assumption A-02).
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    // Lines keep the order in which they were entered.
    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class)->orderBy('id');
    }
}
