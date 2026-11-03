<?php

namespace App\Http\Requests;

use Brick\Math\BigDecimal;
use Illuminate\Foundation\Http\FormRequest;

class GoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Access is enforced by the permission:goods_receipts.manage route middleware.
        return true;
    }

    // Checks the SHAPE of the form. The receiving rules (over-receipt, order status) live in
    // GoodsReceiptService, which checks everything again before saving.
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_item_id' => ['required', 'integer', 'exists:purchase_order_items,id'],
            // A blank (or zero) quantity means "nothing arrived on this line". receivedLines() drops those.
            // The limit is the largest value that fits DECIMAL(12,3).
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:999999999.999', 'decimal:0,3'],
        ];
    }

    // :position is the line number on the screen. The page sends EVERY line, so it matches what the user sees.
    public function attributes(): array
    {
        return [
            'lines.*.purchase_order_item_id' => 'order line of line :position',
            'lines.*.quantity' => 'quantity of line :position',
        ];
    }

    // The lines that really received something, as exact decimal strings.
    public function receivedLines(): array
    {
        $lines = [];

        foreach ($this->validated()['lines'] as $line) {
            $quantity = $line['quantity'] ?? null;

            if ($quantity === null || $quantity === '') {
                continue; // blank: nothing arrived
            }

            if (! BigDecimal::of((string) $quantity)->isPositive()) {
                continue; // zero: nothing arrived
            }

            $lines[] = [
                'purchase_order_item_id' => (int) $line['purchase_order_item_id'],
                'quantity' => (string) $quantity,
            ];
        }

        return $lines;
    }
}