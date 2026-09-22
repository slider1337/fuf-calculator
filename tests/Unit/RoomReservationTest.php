<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Shared\ValueObject\Percentage;
use App\Domain\Trip\DistributionMethod;
use App\Domain\Trip\RoomReservation;
use App\Domain\Trip\Trip;
use App\Domain\Trip\TripPricingPolicy;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RoomReservationTest extends TestCase
{
    public function testRoomTypeIsTrimmed(): void
    {
        $reservation = new RoomReservation('  2-Bettzimmer ', 4);

        self::assertSame('2-Bettzimmer', $reservation->roomType());
        self::assertSame(4, $reservation->count());
    }

    public function testEmptyRoomTypeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RoomReservation('   ', 1);
    }

    public function testNegativeCountThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RoomReservation('2-Bettzimmer', -1);
    }

    public function testZeroCountIsAllowed(): void
    {
        self::assertSame(0, (new RoomReservation('2-Bettzimmer', 0))->count());
    }

    public function testMatchKeyIgnoresCaseAndOuterWhitespace(): void
    {
        self::assertSame(
            (new RoomReservation('2-Bettzimmer', 1))->matchKey(),
            (new RoomReservation(' 2-BETTZIMMER  ', 1))->matchKey()
        );
    }

    public function testTripWithoutReservationsHasEmptyList(): void
    {
        self::assertSame([], $this->trip()->roomReservations());
    }

    public function testTripKeepsReservationsThroughWithId(): void
    {
        $reservations = [new RoomReservation('2-Bettzimmer', 4), new RoomReservation('Familienzimmer', 1)];

        $trip = $this->trip($reservations)->withId(7);

        self::assertSame($reservations, $trip->roomReservations());
    }

    public function testTripRejectsSameRoomTypeTwice(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->trip([new RoomReservation('2-Bettzimmer', 4), new RoomReservation('2-bettzimmer ', 1)]);
    }

    /** @param RoomReservation[] $reservations */
    private function trip(array $reservations = []): Trip
    {
        return new Trip(
            id: null,
            name: 'Testreise',
            startDate: new DateTimeImmutable('2026-02-14'),
            pricingPolicy: new TripPricingPolicy(
                new Percentage(10.0),
                new Percentage(5.0),
                DistributionMethod::PER_PERSON,
                2.5,
                18,
                16
            ),
            bookings: [],
            groupExpenses: [],
            plannedTotalCosts: 0.0,
            plannedTotalRevenue: 0.0,
            roomReservations: $reservations,
        );
    }
}
