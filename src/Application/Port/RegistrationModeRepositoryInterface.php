<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Application\Data\RegistrationModeState;
use App\Domain\Trip\RegistrationMode;

interface RegistrationModeRepositoryInterface
{
    public function state(int $trip): ?RegistrationModeState;
    public function hasActiveOperations(int $trip): bool;
    public function hasManagedBookings(int $trip): bool;
    public function hasApprovedGuests(int $trip): bool;
    public function disableMemberRegistration(int $trip): void;
    public function change(int $trip, RegistrationMode $mode): void;
}
