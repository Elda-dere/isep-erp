<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Access is enforced by the permission:customers.manage route middleware.
        return true;
    }

    // The limits match the column sizes in the schema.
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:180',
                // The name "Walk-in customer" belongs to the seeded record only.
                function (string $attribute, mixed $value, Closure $fail) {
                    $editing = $this->route('customer'); // null when creating
                    $isTheWalkIn = $editing instanceof Customer && $editing->isWalkIn();

                    if (! $isTheWalkIn && Customer::isWalkInName((string) $value)) {
                        $fail('This name is reserved for the Walk-in customer.');
                    }
                },
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:190'],
            'address' => ['nullable', 'string'],
        ];
    }
}
