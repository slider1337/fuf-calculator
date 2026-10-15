<?php

declare(strict_types=1);

namespace App\Domain\Registration;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class Participant
{
    public function __construct(
        private string $name,
        private ?DateTimeImmutable $birthDate,
        private ?int $id = null,
        private string $status = 'confirmed'
    ) {
        if ($name === '') {
            throw new InvalidArgumentException('Participant name is required.');
        }
    }

    public function id(): ?int { return $this->id; }
    public function status(): string { return $this->status; }

    public function name(): string
    {
        return $this->name;
    }

    public function birthDate(): ?DateTimeImmutable
    {
        return $this->birthDate;
    }

    public function ageAtDate(DateTimeImmutable $referenceDate): int
    {
        if($this->birthDate===null)throw new InvalidArgumentException('Birth date is required to calculate age.');
        return $this->birthDate->diff($referenceDate)->y;
    }
}

