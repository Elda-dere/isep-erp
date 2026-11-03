<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptItem extends Model
{
    public $timestamps = false; // the table has no created_at/updated_at

    protected $fillable = ['goods_receipt_id', 'purchase_order_item_id', 'product_id', 'quantity_received'];

    // The cast keeps quantities as exact decimal strings, never floats.
    protected function casts(): array
    {
        return [
            'quantity_received' => 'decimal:3',
        ];
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
