<?php

namespace App\Exceptions;

use RuntimeException;

class AutotraderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable = false,
        public readonly bool $needsAction = false,
        public readonly ?int $status = null,
    ) {
        parent::__construct($message);
    }
}
