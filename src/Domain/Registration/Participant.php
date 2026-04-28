<?php

declare(strict_types=1);

namespace App\Domain\Registration;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class Participant
{
    public function __construct(
        private string $name,
        private DateTimeImmutable $birthDate
    ) {
        if ($name === '') {
            throw new InvalidArgumentException('Participant name is required.');
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function birthDate(): DateTimeImmutable
    {
        return $this->birthDate;
    }

    public function ageAtDate(DateTimeImmutable $referenceDate): int
    {
        return $this->birthDate->diff($referenceDate)->y;
    }
}

