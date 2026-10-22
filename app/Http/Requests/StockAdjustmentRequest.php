<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Access is enforced by the permission:inventory.adjust route middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            // Signed: positive adds stock, negative removes it. Zero is refused by StockService.
            'quantity' => ['required', 'numeric', 'min:-999999999.999', 'max:999999999.999', 'decimal:0,3'],
            'note' => ['required', 'string', 'max:1000'],
        ];
    }
}
