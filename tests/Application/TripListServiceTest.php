<?php

declare(strict_types=1);

namespace Tests\Application;

use App\Application\ActualExpenseService;
use App\Application\Port\RegistrationRepositoryInterface;
use App\Application\TripListService;
use App\Domain\Registration\Participant;
use App\Domain\Registration\Registration;
use App\Domain\Service\PriceCalculatorService;
use App\Domain\Shared\ValueObject\Percentage;
use App\Domain\Trip\DistributionMethod;
use App\Domain\Trip\GroupExpense;
use App\Domain\Trip\RoomBooking;
use App\Domain\Trip\RoomCategoryType;
use App\Domain\Trip\Trip;
use App\Domain\Trip\TripPricingPolicy;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TripListServiceTest extends TestCase
{
    private InMemoryTripRepository $tripRepo;
    private InMemoryRegistrationRepository $regRepo;
    private PriceCalculatorService $calculator;
    private ActualExpenseService $expenseService;

    protected function setUp(): void
    {
        $this->tripRepo = new InMemoryTripRepository();
        $this->regRepo = new InMemoryRegistrationRepository();
        $this->calculator = new PriceCalculatorService();
        $this->expenseService = new ActualExpenseService(
            new InMemoryActualExpenseRepository(),
            $this->tripRepo,
            $this->regRepo
        );
    }

    private function service(?RegistrationRepositoryInterface $registrationRepository = null): TripListService
    {
        return new TripListService(
            $this->tripRepo,
            $registrationRepository ?? $this->regRepo,
            $this->calculator,
            $this->expenseService
        );
    }

    private function saveTrip(?DateTimeImmutable $endDate = null): Trip
    {
        return $this->tripRepo->save(new Trip(
            id: null,
            name: 'Winterfreizeit',
            startDate: new DateTimeImmutable('2026-12-20'),
            pricingPolicy: new TripPricingPolicy(
                new Percentage(10.0),
                new Percentage(5.0),
                DistributionMethod::PER_PERSON,
                2.0,
                18
            ),
            bookings: [
                new RoomBooking(RoomCategoryType::ADULT_DOUBLE, 3, 100.0),
                new RoomBooking(RoomCategoryType::ADULT_MULTI, 2, 90.0),
                new RoomBooking(RoomCategoryType::CHILD, 1, 60.0),
            ],
            groupExpenses: [new GroupExpense('Snacks', 30.0)],
            plannedTotalCosts: 0.0,
            plannedTotalRevenue: 0.0,
            spaTaxCount: 5,
            endDate: $endDate ?? new DateTimeImmutable('2026-12-23')
        ));
    }

    private function saveRegistration(int $tripId, int $participantCount, ?float $billingTotal = null): void
    {
        $participants = [];
        for ($i = 0; $i < $participantCount; $i++) {
            $participants[] = new Participant('Teilnehmer ' . $i, new DateTimeImmutable('1990-01-01'));
        }

        $registration = new Registration(
            id: null,
            tripId: $tripId,
            roomCategory: 'DZ',
            receivedAt: '2026-01-01',
            comment: '',
            participants: $participants
        );

        if ($billingTotal !== null) {
            $registration = $registration->withBilling(new DateTimeImmutable(), $billingTotal, []);
        }

        $this->regRepo->save($registration);
    }

    public function testListItemCarriesPeriodAndParticipantCounts(): void
    {
        $trip = $this->saveTrip();
        $this->saveRegistration((int) $trip->id(), 2);

        $items = $this->service()->listTrips();

        self::assertCount(1, $items);
        self::assertSame($trip->id(), $items[0]['id']);
        self::assertSame('Winterfreizeit', $items[0]['name']);
        self::assertSame('2026-12-20', $items[0]['startDate']);
        self::assertSame('2026-12-23', $items[0]['endDate']);
        self::assertSame(3, $items[0]['nights']);
        self::assertSame(6, $items[0]['plannedParticipants']);
        self::assertSame(2, $items[0]['registeredParticipants']);
    }

    public function testSurplusComesFromCalculationWhenNoRegistrationsExist(): void
    {
        $trip = $this->saveTrip();

        $items = $this->service()->listTrips();

        self::assertSame('calculation', $items[0]['surplusBasis']);
        self::assertSame($this->calculator->calculate($trip)['surplus'], $items[0]['surplus']);
    }

    public function testSurplusComesFromSettlementOnceRegistrationsExist(): void
    {
        $trip = $this->saveTrip();
        $this->saveRegistration((int) $trip->id(), 2, 600.0);

        $items = $this->service()->listTrips();

        self::assertSame('settlement', $items[0]['surplusBasis']);
        self::assertSame(
            $this->expenseService->getSettlement((int) $trip->id())['surplus'],
            $items[0]['surplus']
        );
    }

    public function testUncomputableTripYieldsNullSurplusInsteadOfBreakingTheList(): void
    {
        $trip = $this->saveTrip();

        $items = $this->service(new FailingRegistrationRepository())->listTrips();

        self::assertCount(1, $items);
        self::assertSame($trip->id(), $items[0]['id']);
        self::assertSame('Winterfreizeit', $items[0]['name']);
        self::assertNull($items[0]['surplus']);
        self::assertNull($items[0]['surplusBasis']);
    }
}

final class FailingRegistrationRepository implements RegistrationRepositoryInterface
{
    public function save(Registration $registration): Registration
    {
        throw new RuntimeException('not supported');
    }

    public function updateBilling(Registration $registration): Registration
    {
        throw new RuntimeException('not supported');
    }

    public function findByTripId(int $tripId): array
    {
        throw new RuntimeException('registration storage unavailable');
    }

    public function deleteByTripId(int $tripId): void
    {
        throw new RuntimeException('not supported');
    }
}
