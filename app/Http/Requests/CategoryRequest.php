<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Access is enforced by the permission:products.manage route middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],   // categories.name is varchar(100)
            'description' => ['nullable', 'string'],        // text column, optional
        ];
    }
}
