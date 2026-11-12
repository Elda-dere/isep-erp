<?php

namespace App\Exceptions;

use RuntimeException;

// Thrown when Chapa cannot be reached or refuses a request. The message is safe to show: it never contains the key.
class ChapaException extends RuntimeException
{
}