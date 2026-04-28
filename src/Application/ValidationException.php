<?php

declare(strict_types=1);

namespace App\Application;

use RuntimeException;

final class ValidationException extends RuntimeException
{
    public function __construct(private readonly array $errors)
    {
        parent::__construct('Validation failed.');
    }

    public function errors(): array
    {
        return $this->errors;
    }
}

