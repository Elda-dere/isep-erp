<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public const UPDATED_AT = null; // table only has created_at

    public const TYPE_OPENING = 'opening';
    public const TYPE_RECEIPT = 'receipt';
    public const TYPE_SALE = 'sale';
    public const TYPE_SALE_REVERSAL = 'sale_reversal';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_TRANSFER_IN = 'transfer_in';
    public const TYPE_TRANSFER_OUT = 'transfer_out';
    // All allowed movement types in one list: used to validate filters and fill dropdowns.
    public const TYPES = [
        self::TYPE_OPENING,
        self::TYPE_RECEIPT,
        self::TYPE_SALE,
        self::TYPE_SALE_REVERSAL,
        self::TYPE_ADJUSTMENT,
        self::TYPE_TRANSFER_IN,
        self::TYPE_TRANSFER_OUT,
    ];

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'movement_type',
        'quantity',
        'goods_receipt_item_id',
        'sale_item_id',
        'performed_by',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
