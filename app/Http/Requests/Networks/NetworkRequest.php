<?php

namespace App\Http\Requests\Networks;

use App\Rules\Cidr;
use App\Support\IpRange;
use Illuminate\Foundation\Http\FormRequest;

class NetworkRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route middleware already checks the networks.manage permission.
        return true;
    }

    /** A valid range is saved from its first address, so 192.168.1.77/24 becomes 192.168.1.0/24. */
    protected function prepareForValidation(): void
    {
        $value = trim((string) $this->input('ip_range'));

        $this->merge([
            'ip_range' => IpRange::valid($value) ? IpRange::normalize($value) : $value,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'ip_range' => ['required', 'string', 'max:50', new Cidr],
        ];
    }
}