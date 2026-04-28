<?php

declare(strict_types=1);

namespace Tests\Application;

use App\Application\NotFoundException;
use App\Application\Port\TripRepositoryInterface;
use App\Application\TripService;
use App\Application\ValidationException;
use App\Domain\Service\PriceCalculatorService;
use App\Domain\Trip\Trip;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TripServiceTest extends TestCase
{
    public function testCreateFromArrayRequiresAllFields(): void
    {
        $service = new TripService(new InMemoryTripRepository(), new PriceCalculatorService());

        $this->expectException(ValidationException::class);
        $service->createFromArray(['name' => 'Unvollstaendig']);
    }

    public function testGetTripThrowsNotFoundForUnknownId(): void
    {
        $service = new TripService(new InMemoryTripRepository(), new PriceCalculatorService());

        $this->expectException(NotFoundException::class);
        $service->getTrip(9999);
    }
}

final class InMemoryTripRepository implements TripRepositoryInterface
{
    /** @var array<int, Trip> */
    private array $items = [];

    private int $nextId = 1;

    public function save(Trip $trip): Trip
    {
        $id = $this->nextId++;
        $saved = $trip->withId($id);
        $this->items[$id] = $saved;

        return $saved;
    }

    public function getById(int $id): ?Trip
    {
        return $this->items[$id] ?? null;
    }

    public function update(Trip $trip): Trip
    {
        $id = $trip->id();
        if ($id === null) {
            throw new InvalidArgumentException('Trip id is required.');
        }

        $this->items[$id] = $trip;

        return $trip;
    }

    public function findAll(): array
    {
        $rows = [];
        foreach ($this->items as $trip) {
            $rows[] = [
                'id' => (int) $trip->id(),
                'name' => $trip->name(),
                'startDate' => $trip->startDate()->format('Y-m-d'),
            ];
        }

        return $rows;
    }
}


