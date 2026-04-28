<?php

declare(strict_types=1);

namespace App\Domain\Service;

final class PerPersonDistributionStrategy implements ExpenseDistributionStrategy
{
    public function denominator(array $bookings): int
    {
        $sum = 0;
        foreach ($bookings as $booking) {
            $sum += $booking->count();
        }

        return $sum;
    }
}

