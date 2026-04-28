<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Shared\ValueObject\Percentage;
use App\Domain\Trip\ActualExpense;
use App\Domain\Trip\GroupExpense;
use App\Domain\Trip\RoomBooking;
use App\Domain\Trip\RoomCategoryType;
use App\Domain\Trip\Trip;
use App\Domain\Trip\TripPricingPolicy;
use App\Domain\Trip\DistributionMethod;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DomainValidationTest extends TestCase
{
    public function testPercentageValidRange(): void
    {
        $p = new Percentage(50.0);
        self::assertSame(50.0, $p->value());
        self::assertSame(0.5, $p->factor());
    }

    public function testPercentageZero(): void
    {
        $p = new Percentage(0.0);
        self::assertSame(0.0, $p->value());
        self::assertSame(0.0, $p->factor());
    }

    public function testPercentageHundred(): void
    {
        $p = new Percentage(100.0);
        self::assertSame(100.0, $p->value());
        self::assertSame(1.0, $p->factor());
    }

    public function testPercentageNegativeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Percentage(-1.0);
    }

    public function testPercentageAbove100Throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Percentage(100.01);
    }

    public function testPercentageRoundsValue(): void
    {
        $p = new Percentage(33.333);
        self::assertSame(33.33, $p->value());
    }

    public function testGroupExpenseValid(): void
    {
        $e = new GroupExpense('Snacks', 30.0);
        self::assertSame('Snacks', $e->label());
        self::assertSame(30.0, $e->amount());
    }

    public function testGroupExpenseEmptyLabelThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new GroupExpense('', 10.0);
    }

    public function testGroupExpenseNegativeAmountThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new GroupExpense('Test', -1.0);
    }

    public function testGroupExpenseAmountRounds(): void
    {
        $e = new GroupExpense('Rounding', 10.999);
        self::assertSame(11.0, $e->amount());
    }

    public function testRoomBookingValid(): void
    {
        $b = new RoomBooking(RoomCategoryType::ADULT_DOUBLE, 3, 100.0);
        self::assertSame(RoomCategoryType::ADULT_DOUBLE, $b->categoryType());
        self::assertSame(3, $b->count());
        self::assertSame(100.0, $b->basePricePerPerson());
    }

    public function testRoomBookingNegativeCountThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RoomBooking(RoomCategoryType::CHILD, -1, 50.0);
    }

    public function testRoomBookingNegativePriceThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RoomBooking(RoomCategoryType::CHILD, 1, -10.0);
    }

    public function testRoomBookingPriceRounds(): void
    {
        $b = new RoomBooking(RoomCategoryType::ADULT_MULTI, 1, 99.999);
        self::assertSame(100.0, $b->basePricePerPerson());
    }

    public function testRoomBookingZeroCountIsAllowed(): void
    {
        $b = new RoomBooking(RoomCategoryType::CHILD, 0, 50.0);
        self::assertSame(0, $b->count());
    }

    public function testTripEmptyNameThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Trip(
            null,
            '',
            new DateTimeImmutable('2026-01-01'),
            new TripPricingPolicy(
                new Percentage(10.0),
                new Percentage(5.0),
                DistributionMethod::PER_PERSON,
                2.0,
                18
            ),
            [new RoomBooking(RoomCategoryType::ADULT_DOUBLE, 1, 100.0)],
            [],
            0.0,
            0.0
        );
    }

    public function testTripNegativePlannedCostsThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Trip(
            null,
            'Test',
            new DateTimeImmutable('2026-01-01'),
            new TripPricingPolicy(
                new Percentage(10.0),
                new Percentage(5.0),
                DistributionMethod::PER_PERSON,
                2.0,
                18
            ),
            [new RoomBooking(RoomCategoryType::ADULT_DOUBLE, 1, 100.0)],
            [],
            -100.0,
            0.0
        );
    }

    public function testTripNegativePlannedRevenueThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Trip(
            null,
            'Test',
            new DateTimeImmutable('2026-01-01'),
            new TripPricingPolicy(
                new Percentage(10.0),
                new Percentage(5.0),
                DistributionMethod::PER_PERSON,
                2.0,
                18
            ),
            [new RoomBooking(RoomCategoryType::ADULT_DOUBLE, 1, 100.0)],
            [],
            0.0,
            -50.0
        );
    }

    public function testTripPlannedTotalsRound(): void
    {
        $trip = new Trip(
            null,
            'Round Test',
            new DateTimeImmutable('2026-01-01'),
            new TripPricingPolicy(
                new Percentage(10.0),
                new Percentage(5.0),
                DistributionMethod::PER_PERSON,
                2.0,
                18
            ),
            [new RoomBooking(RoomCategoryType::ADULT_DOUBLE, 1, 100.0)],
            [],
            100.999,
            200.555
        );

        self::assertSame(101.0, $trip->plannedTotalCosts());
        self::assertSame(200.56, $trip->plannedTotalRevenue());
    }

    public function testTripPricingPolicySpaTaxRounds(): void
    {
        $policy = new TripPricingPolicy(
            new Percentage(10.0),
            new Percentage(5.0),
            DistributionMethod::PER_PERSON,
            2.999,
            18
        );

        self::assertSame(3.0, $policy->spaTaxPerPerson());
        self::assertSame(18, $policy->spaTaxAgeThreshold());
    }

    public function testActualExpenseValid(): void
    {
        $e = new ActualExpense(null, 1, 'Unterkunft', 500.0);
        self::assertNull($e->id());
        self::assertSame(1, $e->tripId());
        self::assertSame('Unterkunft', $e->label());
        self::assertSame(500.0, $e->amount());
    }

    public function testActualExpenseWithId(): void
    {
        $e = new ActualExpense(null, 1, 'Test', 100.0);
        $withId = $e->withId(42);
        self::assertSame(42, $withId->id());
        self::assertSame(1, $withId->tripId());
    }

    public function testActualExpenseEmptyLabelThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ActualExpense(null, 1, '', 100.0);
    }

    public function testActualExpenseNegativeAmountThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ActualExpense(null, 1, 'Test', -10.0);
    }

    public function testActualExpenseAmountRounds(): void
    {
        $e = new ActualExpense(null, 1, 'Rounding', 99.999);
        self::assertSame(100.0, $e->amount());
    }

    public function testActualExpenseZeroAmountIsAllowed(): void
    {
        $e = new ActualExpense(null, 1, 'Free', 0.0);
        self::assertSame(0.0, $e->amount());
    }
}



