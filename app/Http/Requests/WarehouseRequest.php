<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Access is enforced by the permission:warehouses.manage route middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:255'],
        ];
    }
}
