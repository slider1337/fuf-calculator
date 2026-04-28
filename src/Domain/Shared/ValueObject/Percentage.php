<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObject;

use InvalidArgumentException;

final class Percentage
{
    public function __construct(private float $value)
    {
        if ($value < 0 || $value > 100) {
            throw new InvalidArgumentException('Percentage must be between 0 and 100.');
        }
    }

    public function value(): float
    {
        return round($this->value, 2, PHP_ROUND_HALF_UP);
    }

    public function factor(): float
    {
        return round($this->value() / 100, 4, PHP_ROUND_HALF_UP);
    }
}

