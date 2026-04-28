<?php

declare(strict_types=1);

namespace App\Domain\Trip;

use InvalidArgumentException;

final class GroupExpense
{
    public function __construct(private string $label, private float $amount)
    {
        if ($label === '') {
            throw new InvalidArgumentException('Label is required.');
        }
        if ($amount < 0) {
            throw new InvalidArgumentException('Amount must be >= 0.');
        }
    }

    public function label(): string
    {
        return $this->label;
    }

    public function amount(): float
    {
        return round($this->amount, 2, PHP_ROUND_HALF_UP);
    }
}

