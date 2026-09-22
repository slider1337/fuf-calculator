<?php

declare(strict_types=1);

namespace App\Domain\Trip;

use InvalidArgumentException;

/**
 * Beim Haus reservierte Zimmer eines Typs. Der Typ ist freier Text und wird ueber
 * matchKey() den Zimmerkategorien der Anmeldungen zugeordnet.
 */
final readonly class RoomReservation
{
    private string $roomType;

    public function __construct(string $roomType, private int $count)
    {
        $this->roomType = trim($roomType);

        if ($this->roomType === '') {
            throw new InvalidArgumentException('Room type is required.');
        }

        if ($count < 0) {
            throw new InvalidArgumentException('Count must be >= 0.');
        }
    }

    public static function matchKeyFor(string $roomType): string
    {
        return mb_strtolower(trim($roomType));
    }

    public function roomType(): string
    {
        return $this->roomType;
    }

    public function count(): int
    {
        return $this->count;
    }

    public function matchKey(): string
    {
        return self::matchKeyFor($this->roomType);
    }
}
