<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Access is enforced by the permission:products.manage route middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],     // units.name is varchar(50)
            'symbol' => ['required', 'string', 'max:20'],   // units.symbol is varchar(20), for example "kg"
        ];
    }
}
