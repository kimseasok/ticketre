<?php

namespace App\Exceptions;

use RuntimeException;

class TwoFactorException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        protected readonly string $errorCode,
        string $message,
        protected readonly int $status = 422,
        protected readonly array $context = []
    ) {
        parent::__construct($message, $status);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
