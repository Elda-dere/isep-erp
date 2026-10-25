<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            // The person may keep their own email.
            'email' => ['required', 'string', 'email', 'max:190', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['integer', 'distinct', 'exists:roles,id'],
        ];
    }
}