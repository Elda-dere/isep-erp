<?php

namespace App\Rules;

use App\Support\IpRange;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Cidr implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! IpRange::valid($value)) {
            $fail('Enter a range like 192.168.1.0/24 or 2001:db8::/32.');

            return;
        }

        if (IpRange::prefix($value) === 0) {
            $fail('This range would allow every address on the internet. Use a narrower range.');
        }
    }
}