<?php

declare(strict_types=1);

namespace App\Domain\Service;

final class PerCategoryUnitsDistributionStrategy implements ExpenseDistributionStrategy
{
    public function denominator(array $bookings): int
    {
        return count(array_filter($bookings, static fn ($booking) => $booking->count() > 0));
    }
}

