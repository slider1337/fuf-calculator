<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObject;

final class Money
{
    public function __construct(private float $amount)
    {
    }

    public static function fromFloat(float $amount): self
    {
        return new self(self::round2($amount));
    }

    public function add(self $other): self
    {
        return new self(self::round2($this->amount + $other->amount));
    }

    public function multiply(float $factor): self
    {
        return new self(self::round2($this->amount * $factor));
    }

    public function value(): float
    {
        return self::round2($this->amount);
    }

    private static function round2(float $value): float
    {
        return round($value, 2, PHP_ROUND_HALF_UP);
    }
}

