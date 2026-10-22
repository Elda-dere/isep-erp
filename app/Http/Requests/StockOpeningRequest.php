<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockOpeningRequest extends FormRequest
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
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:999999999.999', 'decimal:0,3'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
