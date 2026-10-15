<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Registration\Registration;

interface RegistrationRepositoryInterface
{
    public function save(Registration $registration): Registration;

    public function updateBilling(Registration $registration): Registration;

    /** @return Registration[] */
    public function findByTripId(int $tripId): array;

    public function findById(int $id): ?Registration;

    public function deleteByTripId(int $tripId): void;

    public function deleteCsvByTripId(int $tripId): void;

    public function deleteById(int $id): void;
}

