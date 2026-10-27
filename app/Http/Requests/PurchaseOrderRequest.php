<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Access is enforced by the permission:purchase_orders.manage route middleware.
        return true;
    }

    // Checks the SHAPE of the form. The business rules (inactive products, statuses) live in
    // PurchaseOrderService, which checks everything again before saving.
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            // The limits are the largest values that fit DECIMAL(12,3) and DECIMAL(12,2).
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999.999', 'decimal:0,3'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
        ];
    }

    // Friendly names for the error messages. :position is the line number (1, 2, 3 ...).
    public function attributes(): array
    {
        return [
            'supplier_id' => 'supplier',
            'lines.*.product_id' => 'product of line :position',
            'lines.*.quantity' => 'quantity of line :position',
            'lines.*.unit_cost' => 'unit cost of line :position',
        ];
    }
}
