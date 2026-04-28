<?php

declare(strict_types=1);

namespace App\Domain\Trip;

use DateTimeImmutable;
use InvalidArgumentException;

final class Trip
{
    /** @param RoomBooking[] $bookings @param GroupExpense[] $groupExpenses */
    public function __construct(
        private ?int $id,
        private string $name,
        private DateTimeImmutable $startDate,
        private TripPricingPolicy $pricingPolicy,
        private array $bookings,
        private array $groupExpenses,
        private float $plannedTotalCosts,
        private float $plannedTotalRevenue
    ) {
        if ($name === '') {
            throw new InvalidArgumentException('Trip name is required.');
        }

        if ($plannedTotalCosts < 0 || $plannedTotalRevenue < 0) {
            throw new InvalidArgumentException('Planned totals must be >= 0.');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->name,
            $this->startDate,
            $this->pricingPolicy,
            $this->bookings,
            $this->groupExpenses,
            $this->plannedTotalCosts,
            $this->plannedTotalRevenue
        );
    }

    public function name(): string
    {
        return $this->name;
    }

    public function startDate(): DateTimeImmutable
    {
        return $this->startDate;
    }

    public function pricingPolicy(): TripPricingPolicy
    {
        return $this->pricingPolicy;
    }

    /** @return RoomBooking[] */
    public function bookings(): array
    {
        return $this->bookings;
    }

    /** @return GroupExpense[] */
    public function groupExpenses(): array
    {
        return $this->groupExpenses;
    }

    public function plannedTotalCosts(): float
    {
        return round($this->plannedTotalCosts, 2, PHP_ROUND_HALF_UP);
    }

    public function plannedTotalRevenue(): float
    {
        return round($this->plannedTotalRevenue, 2, PHP_ROUND_HALF_UP);
    }
}

