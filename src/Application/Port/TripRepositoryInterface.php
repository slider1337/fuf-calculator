<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Trip\Trip;

interface TripRepositoryInterface
{
    public function save(Trip $trip): Trip;

    public function update(Trip $trip): Trip;

    public function getById(int $id): ?Trip;

    /** @return array<int, array{id:int,name:string,startDate:string}> */
    public function findAll(): array;
}


