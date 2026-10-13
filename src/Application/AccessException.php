<?php

declare(strict_types=1);
namespace App\Application;

final class AccessException extends \RuntimeException
{
    public function __construct(public readonly string $error, public readonly int $status = 403)
    {
        parent::__construct($error);
    }
}
