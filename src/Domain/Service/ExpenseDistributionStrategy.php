<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Trip\RoomBooking;

interface ExpenseDistributionStrategy
{
    /** @param RoomBooking[] $bookings */
    public function denominator(array $bookings): int;
}

