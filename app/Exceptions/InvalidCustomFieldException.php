<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidCustomFieldException extends RuntimeException
{
    /**
     * @param  array<string, string>  $errors
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('The provided custom fields payload is invalid.');
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
