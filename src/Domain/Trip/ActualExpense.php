<?php

declare(strict_types=1);

namespace App\Domain\Trip;

use InvalidArgumentException;

final readonly class ActualExpense
{
    public function __construct(
        private ?int $id,
        private int $tripId,
        private string $label,
        private float $amount
    ) {
        if ($label === '') {
            throw new InvalidArgumentException('Label is required.');
        }
        if ($amount < 0) {
            throw new InvalidArgumentException('Amount must be >= 0.');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function withId(int $id): self
    {
        return new self($id, $this->tripId, $this->label, $this->amount);
    }

    public function tripId(): int
    {
        return $this->tripId;
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

