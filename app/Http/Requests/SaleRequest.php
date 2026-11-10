<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Access is enforced by the permission:sales.create route middleware.
        return true;
    }

    // Checks the SHAPE of the form. The business rules (stock, inactive products, price permission)
    // live in SaleService, which checks everything again before saving.
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            // The limits are the largest values that fit DECIMAL(12,3) and DECIMAL(12,2).
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999.999', 'decimal:0,3'],
            // Optional: without it, the service uses the product's selling price.
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
        ];
    }

    // :position is the line number on the screen, so a message like "quantity of line 2" matches
    // the second card the cashier sees.
    public function attributes(): array
    {
        return [
            'customer_id' => 'customer',
            'lines.*.product_id' => 'product of line :position',
            'lines.*.warehouse_id' => 'warehouse of line :position',
            'lines.*.quantity' => 'quantity of line :position',
            'lines.*.unit_price' => 'unit price of line :position',
        ];
    }
}
