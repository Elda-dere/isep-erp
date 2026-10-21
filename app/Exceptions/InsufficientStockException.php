<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly string $available,
        public readonly string $requested,
    ) {
        parent::__construct("Insufficient stock: available {$available}, requested {$requested}.");
    }
}
