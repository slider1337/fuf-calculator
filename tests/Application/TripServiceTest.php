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

    public function testCreateFromArrayMapsAdultAgeThreshold(): void
    {
        $service = new TripService(new InMemoryTripRepository(), new PriceCalculatorService());

        $trip = $service->createFromArray($this->validPayload(['adultAgeThreshold' => 14]));

        self::assertSame(14, $trip->pricingPolicy()->adultAgeThreshold());
    }

    public function testCreateFromArrayWithoutAdultAgeThresholdThrowsValidation(): void
    {
        $service = new TripService(new InMemoryTripRepository(), new PriceCalculatorService());
        $payload = $this->validPayload();
        unset($payload['adultAgeThreshold']);

        $this->expectException(ValidationException::class);
        $service->createFromArray($payload);
    }

    public function testCreateFromArrayMapsAverageAdultPrice(): void
    {
        $service = new TripService(new InMemoryTripRepository(), new PriceCalculatorService());

        $trip = $service->createFromArray($this->validPayload(['averageAdultPrice' => true]));

        self::assertTrue($trip->pricingPolicy()->averageAdultPrice());
    }

    public function testCreateFromArrayWithoutAverageAdultPriceDefaultsToOff(): void
    {
        $service = new TripService(new InMemoryTripRepository(), new PriceCalculatorService());

        $trip = $service->createFromArray($this->validPayload());

        self::assertFalse($trip->pricingPolicy()->averageAdultPrice());
    }

    public function testCreateFromArrayMapsRoomReservations(): void
    {
        $service = new TripService(new InMemoryTripRepository(), new PriceCalculatorService());

        $trip = $service->createFromArray($this->validPayload(['roomReservations' => [
            ['roomType' => '2-Bettzimmer', 'count' => 4],
            ['roomType' => 'Familienzimmer', 'count' => 1],
        ]]));

        $reservations = $trip->roomReservations();
        self::assertCount(2, $reservations);
        self::assertSame('2-Bettzimmer', $reservations[0]->roomType());
        self::assertSame(4, $reservations[0]->count());
        self::assertSame('Familienzimmer', $reservations[1]->roomType());
    }

    public function testCreateFromArrayWithoutRoomReservationsHasNone(): void
    {
        $service = new TripService(new InMemoryTripRepository(), new PriceCalculatorService());

        $trip = $service->createFromArray($this->validPayload());

        self::assertSame([], $trip->roomReservations());
    }

    public function testCreateFromArraySkipsRoomReservationWithoutTypeAndCount(): void
    {
        $service = new TripService(new InMemoryTripRepository(), new PriceCalculatorService());

        $trip = $service->createFromArray($this->validPayload(['roomReservations' => [
            ['roomType' => '2-Bettzimmer', 'count' => 4],
            ['roomType' => '  ', 'count' => 0],
        ]]));

        self::assertCount(1, $trip->roomReservations());
    }

    public function testCreateFromArrayRejectsRoomReservationWithoutTypeButWithCount(): void
    {
        $service = new TripService(new InMemoryTripRepository(), new PriceCalculatorService());

        try {
            $service->createFromArray($this->validPayload(['roomReservations' => [
                ['roomType' => '', 'count' => 2],
            ]]));
            self::fail('ValidationException expected.');
        } catch (ValidationException $exception) {
            self::assertSame(['roomReservations' => 'room_type_required'], $exception->errors());
        }
    }

    public function testCreateFromArrayRejectsDuplicateRoomType(): void
    {
        $service = new TripService(new InMemoryTripRepository(), new PriceCalculatorService());

        try {
            $service->createFromArray($this->validPayload(['roomReservations' => [
                ['roomType' => '2-Bettzimmer', 'count' => 4],
                ['roomType' => ' 2-BETTZIMMER', 'count' => 1],
            ]]));
            self::fail('ValidationException expected.');
        } catch (ValidationException $exception) {
            self::assertSame(['roomReservations' => 'duplicate_room_type'], $exception->errors());
        }
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Testreise',
            'startDate' => '2026-02-14',
            'endDate' => '2026-02-17',
            'markupPercent' => 10.0,
            'clubFeePercent' => 5.0,
            'distributionMethod' => 'PER_PERSON',
            'spaTaxPerPerson' => 2.5,
            'spaTaxAgeThreshold' => 18,
            'adultAgeThreshold' => 16,
            'spaTaxCount' => 5,
            'bookings' => [
                ['categoryType' => 'ADULT_DOUBLE', 'count' => 4, 'basePricePerPerson' => 100.0],
                ['categoryType' => 'ADULT_MULTI', 'count' => 6, 'basePricePerPerson' => 90.0],
                ['categoryType' => 'CHILD', 'count' => 5, 'basePricePerPerson' => 60.0],
            ],
            'groupExpenses' => [
                ['label' => 'Getraenke', 'amount' => 75.0],
            ],
        ], $overrides);
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
                'endDate' => $trip->endDate()?->format('Y-m-d'),
            ];
        }

        return $rows;
    }
}


