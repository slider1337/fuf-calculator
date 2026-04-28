<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Registration\Participant;
use App\Domain\Registration\Registration;
use App\Domain\Service\PriceCalculatorService;
use App\Domain\Service\RegistrationBillingService;
use App\Domain\Shared\ValueObject\Percentage;
use App\Domain\Trip\DistributionMethod;
use App\Domain\Trip\GroupExpense;
use App\Domain\Trip\RoomBooking;
use App\Domain\Trip\RoomCategoryType;
use App\Domain\Trip\Trip;
use App\Domain\Trip\TripPricingPolicy;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RegistrationBillingServiceTest extends TestCase
{
    private function createTrip(): Trip
    {
        return new Trip(
            1,
            'Winterfreizeit 2026',
            new DateTimeImmutable('2026-02-14'),
            new TripPricingPolicy(
                new Percentage(10.0),
                new Percentage(5.0),
                DistributionMethod::PER_PERSON,
                2.50,
                18
            ),
            [
                new RoomBooking(RoomCategoryType::ADULT_DOUBLE, 4, 100.00),
                new RoomBooking(RoomCategoryType::ADULT_MULTI, 6, 90.00),
                new RoomBooking(RoomCategoryType::CHILD, 5, 60.00),
            ],
            [new GroupExpense('Getraenke', 75.00)],
            700.00,
            800.00
        );
    }

    private function createService(): RegistrationBillingService
    {
        return new RegistrationBillingService(new PriceCalculatorService());
    }

    public function testDetermineCategoryAdultInDoubleRoom(): void
    {
        $service = $this->createService();
        $adult = new Participant('Max Mustermann', new DateTimeImmutable('1985-03-07'));
        $tripStart = new DateTimeImmutable('2026-02-14');

        $result = $service->determineCategory($adult, '2-Bettzimmer', $tripStart, 18);

        self::assertSame('ADULT_DOUBLE', $result);
    }

    public function testDetermineCategoryAdultInMultiRoom(): void
    {
        $service = $this->createService();
        $adult = new Participant('Max Mustermann', new DateTimeImmutable('1985-03-07'));
        $tripStart = new DateTimeImmutable('2026-02-14');

        self::assertSame('ADULT_MULTI', $service->determineCategory($adult, '3-Bettzimmer', $tripStart, 18));
        self::assertSame('ADULT_MULTI', $service->determineCategory($adult, '4-Bettzimmer', $tripStart, 18));
        self::assertSame('ADULT_MULTI', $service->determineCategory($adult, '5-Bettzimmer', $tripStart, 18));
    }

    public function testDetermineCategoryChildInAnyRoom(): void
    {
        $service = $this->createService();
        $child = new Participant('Klein Kind', new DateTimeImmutable('2015-06-15'));
        $tripStart = new DateTimeImmutable('2026-02-14');

        self::assertSame('CHILD', $service->determineCategory($child, '2-Bettzimmer', $tripStart, 18));
        self::assertSame('CHILD', $service->determineCategory($child, '4-Bettzimmer', $tripStart, 18));
    }

    public function testDetermineCategoryJustTurned18IsAdult(): void
    {
        $service = $this->createService();
        $participant = new Participant('Genau 18', new DateTimeImmutable('2008-02-14'));
        $tripStart = new DateTimeImmutable('2026-02-14');

        self::assertSame('ADULT_MULTI', $service->determineCategory($participant, '3-Bettzimmer', $tripStart, 18));
    }

    public function testDetermineCategoryAlmost18IsChild(): void
    {
        $service = $this->createService();
        $participant = new Participant('Fast 18', new DateTimeImmutable('2008-02-15'));
        $tripStart = new DateTimeImmutable('2026-02-14');

        self::assertSame('CHILD', $service->determineCategory($participant, '3-Bettzimmer', $tripStart, 18));
    }

    public function testCalculateBillingsReturnsCorrectItems(): void
    {
        $service = $this->createService();
        $trip = $this->createTrip();

        $registrations = [
            new Registration(
                1,
                $trip->id(),
                '3-Bettzimmer',
                '28.09.2025 21:34:11',
                '',
                [
                    new Participant('Anna Testperson', new DateTimeImmutable('1985-03-07')),
                    new Participant('Tim Testperson', new DateTimeImmutable('2015-04-08')),
                    new Participant('Lena Testperson', new DateTimeImmutable('2017-10-05')),
                ]
            ),
        ];

        $result = $service->calculateBillings($trip, $registrations);

        self::assertCount(1, $result);
        $billing = $result[0];
        self::assertNotNull($billing->billingCalculatedAt());
        self::assertNotNull($billing->billingTotal());
        self::assertCount(3, $billing->billingItems());

        $items = $billing->billingItems();
        self::assertSame('Anna Testperson', $items[0]->participantName());
        self::assertSame('ADULT_MULTI', $items[0]->categoryType());

        self::assertSame('Tim Testperson', $items[1]->participantName());
        self::assertSame('CHILD', $items[1]->categoryType());

        self::assertSame('Lena Testperson', $items[2]->participantName());
        self::assertSame('CHILD', $items[2]->categoryType());
    }

    public function testCalculateBillingsTotalMatchesSumOfItems(): void
    {
        $service = $this->createService();
        $trip = $this->createTrip();

        $registrations = [
            new Registration(
                1,
                $trip->id(),
                '2-Bettzimmer',
                null,
                '',
                [
                    new Participant('Fritz Beispiel', new DateTimeImmutable('1980-05-13')),
                    new Participant('Maria Beispiel', new DateTimeImmutable('1976-02-01')),
                ]
            ),
        ];

        $result = $service->calculateBillings($trip, $registrations);

        $billing = $result[0];
        $itemSum = 0.0;
        foreach ($billing->billingItems() as $item) {
            $itemSum = round($itemSum + $item->price(), 2, PHP_ROUND_HALF_UP);
        }

        self::assertSame($billing->billingTotal(), $itemSum);
    }

    public function testCalculateBillingsWithEmptyRegistrations(): void
    {
        $service = $this->createService();
        $trip = $this->createTrip();

        $result = $service->calculateBillings($trip, []);

        self::assertSame([], $result);
    }

    public function testCalculateBillingsMultipleRegistrations(): void
    {
        $service = $this->createService();
        $trip = $this->createTrip();

        $registrations = [
            new Registration(1, $trip->id(), '2-Bettzimmer', null, '', [
                new Participant('Erwachsener A', new DateTimeImmutable('1980-01-01')),
            ]),
            new Registration(2, $trip->id(), '4-Bettzimmer', null, '', [
                new Participant('Erwachsener B', new DateTimeImmutable('1975-06-01')),
                new Participant('Kind B', new DateTimeImmutable('2015-03-01')),
            ]),
        ];

        $result = $service->calculateBillings($trip, $registrations);

        self::assertCount(2, $result);
        self::assertNotNull($result[0]->billingTotal());
        self::assertNotNull($result[1]->billingTotal());
        self::assertCount(1, $result[0]->billingItems());
        self::assertCount(2, $result[1]->billingItems());
    }
}

