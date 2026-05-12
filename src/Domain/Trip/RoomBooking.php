<?php

declare(strict_types=1);

namespace App\Domain\Trip;

use InvalidArgumentException;

final readonly class RoomBooking
{
    public function __construct(
        private RoomCategoryType $categoryType,
        private int $count,
        private float $basePricePerPerson,
        private ?float $salesPricePerPerson = null
    ) {
        if ($count < 0) {
            throw new InvalidArgumentException('Count must be >= 0.');
        }

        if ($basePricePerPerson < 0) {
            throw new InvalidArgumentException('Base price must be >= 0.');
        }

        if ($salesPricePerPerson !== null && $salesPricePerPerson < 0) {
            throw new InvalidArgumentException('Sales price must be >= 0.');
        }
    }

    public function categoryType(): RoomCategoryType
    {
        return $this->categoryType;
    }

    public function count(): int
    {
        return $this->count;
    }

    public function basePricePerPerson(): float
    {
        return round($this->basePricePerPerson, 2, PHP_ROUND_HALF_UP);
    }

    public function salesPricePerPerson(): ?float
    {
        return $this->salesPricePerPerson === null
            ? null
            : round($this->salesPricePerPerson, 2, PHP_ROUND_HALF_UP);
    }
}

