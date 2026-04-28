<?php

declare(strict_types=1);

namespace Tests\Application;

use App\Application\ActualExpenseService;
use App\Application\NotFoundException;
use App\Application\Port\ActualExpenseRepositoryInterface;
use App\Application\ValidationException;
use App\Domain\Registration\Participant;
use App\Domain\Registration\Registration;
use App\Domain\Shared\ValueObject\Percentage;
use App\Domain\Trip\ActualExpense;
use App\Domain\Trip\DistributionMethod;
use App\Domain\Trip\RoomBooking;
use App\Domain\Trip\RoomCategoryType;
use App\Domain\Trip\Trip;
use App\Domain\Trip\TripPricingPolicy;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ActualExpenseServiceTest extends TestCase
{
    private InMemoryTripRepository $tripRepo;
    private InMemoryRegistrationRepository $regRepo;
    private ActualExpenseService $service;

    protected function setUp(): void
    {
        $expenseRepo = new InMemoryActualExpenseRepository();
        $this->tripRepo = new InMemoryTripRepository();
        $this->regRepo = new InMemoryRegistrationRepository();
        $this->service = new ActualExpenseService($expenseRepo, $this->tripRepo, $this->regRepo);
    }

    private function createTrip(): Trip
    {
        $trip = new Trip(
            id: null,
            name: 'Test Trip',
            startDate: new DateTimeImmutable('2026-12-20'),
            pricingPolicy: new TripPricingPolicy(
                new Percentage(10.0),
                new Percentage(5.0),
                DistributionMethod::PER_PERSON,
                2.0,
                18
            ),
            bookings: [
                new RoomBooking(RoomCategoryType::ADULT_DOUBLE, 2, 100.0),
                new RoomBooking(RoomCategoryType::ADULT_MULTI, 1, 90.0),
                new RoomBooking(RoomCategoryType::CHILD, 1, 60.0),
            ],
            groupExpenses: [],
            plannedTotalCosts: 0.0,
            plannedTotalRevenue: 0.0
        );

        return $this->tripRepo->save($trip);
    }

    public function testAddExpenseSuccessfully(): void
    {
        $trip = $this->createTrip();

        $expense = $this->service->addExpense($trip->id(), ['label' => 'Unterkunft', 'amount' => 500.0]);

        self::assertNotNull($expense->id());
        self::assertSame('Unterkunft', $expense->label());
        self::assertSame(500.0, $expense->amount());
        self::assertSame($trip->id(), $expense->tripId());
    }

    public function testAddExpenseThrowsNotFoundForUnknownTrip(): void
    {
        $this->expectException(NotFoundException::class);
        $this->service->addExpense(9999, ['label' => 'Test', 'amount' => 10.0]);
    }

    public function testAddExpenseValidationRequiresLabel(): void
    {
        $trip = $this->createTrip();

        $this->expectException(ValidationException::class);
        $this->service->addExpense($trip->id(), ['label' => '', 'amount' => 10.0]);
    }

    public function testAddExpenseValidationRequiresAmount(): void
    {
        $trip = $this->createTrip();

        $this->expectException(ValidationException::class);
        $this->service->addExpense($trip->id(), ['label' => 'Test']);
    }

    public function testAddExpenseValidationRejectsNegativeAmount(): void
    {
        $trip = $this->createTrip();

        $this->expectException(ValidationException::class);
        $this->service->addExpense($trip->id(), ['label' => 'Test', 'amount' => -5.0]);
    }

    public function testUpdateExpenseSuccessfully(): void
    {
        $trip = $this->createTrip();
        $expense = $this->service->addExpense($trip->id(), ['label' => 'Alt', 'amount' => 100.0]);

        $updated = $this->service->updateExpense($expense->id(), ['label' => 'Neu', 'amount' => 200.0]);

        self::assertSame($expense->id(), $updated->id());
        self::assertSame('Neu', $updated->label());
        self::assertSame(200.0, $updated->amount());
    }

    public function testUpdateExpenseThrowsNotFoundForUnknownId(): void
    {
        $this->expectException(NotFoundException::class);
        $this->service->updateExpense(9999, ['label' => 'Test', 'amount' => 10.0]);
    }

    public function testDeleteExpenseSuccessfully(): void
    {
        $trip = $this->createTrip();
        $expense = $this->service->addExpense($trip->id(), ['label' => 'Loeschen', 'amount' => 50.0]);

        $this->service->deleteExpense($expense->id());

        $expenses = $this->service->getExpenses($trip->id());
        self::assertCount(0, $expenses);
    }

    public function testDeleteExpenseThrowsNotFoundForUnknownId(): void
    {
        $this->expectException(NotFoundException::class);
        $this->service->deleteExpense(9999);
    }

    public function testGetExpensesReturnsList(): void
    {
        $trip = $this->createTrip();
        $this->service->addExpense($trip->id(), ['label' => 'A', 'amount' => 10.0]);
        $this->service->addExpense($trip->id(), ['label' => 'B', 'amount' => 20.0]);

        $expenses = $this->service->getExpenses($trip->id());

        self::assertCount(2, $expenses);
    }

    public function testGetExpensesThrowsNotFoundForUnknownTrip(): void
    {
        $this->expectException(NotFoundException::class);
        $this->service->getExpenses(9999);
    }

    public function testGetSettlementWithNoExpenses(): void
    {
        $trip = $this->createTrip();

        $settlement = $this->service->getSettlement($trip->id());

        self::assertSame(0.0, $settlement['totalRevenue']);
        self::assertCount(0, $settlement['expenses']);
        // Planned costs from bookings: 2×100 + 1×90 + 1×60 = 350, spa tax: 4×2 = 8
        self::assertSame(358.0, $settlement['totalPlannedCosts']);
        self::assertSame(0.0, $settlement['totalAdditionalExpenses']);
        self::assertSame(358.0, $settlement['totalAllExpenses']);
        self::assertSame(-358.0, $settlement['surplus']);
        self::assertNotEmpty($settlement['plannedCostItems']);
    }

    public function testGetSettlementCalculatesSurplus(): void
    {
        $trip = $this->createTrip();
        $this->service->addExpense($trip->id(), ['label' => 'A', 'amount' => 100.0]);
        $this->service->addExpense($trip->id(), ['label' => 'B', 'amount' => 50.0]);

        // Add a registration with billing
        $reg = new Registration(
            id: null,
            tripId: $trip->id(),
            roomCategory: 'DZ',
            receivedAt: '2026-01-01',
            comment: '',
            participants: [new Participant('Max Mustermann', new DateTimeImmutable('1990-01-01'))]
        );
        $reg = $reg->withBilling(new DateTimeImmutable(), 600.0, []);
        $this->regRepo->save($reg);

        $settlement = $this->service->getSettlement($trip->id());

        self::assertSame(600.0, $settlement['totalRevenue']);
        self::assertSame(150.0, $settlement['totalAdditionalExpenses']);
        self::assertSame(358.0, $settlement['totalPlannedCosts']);
        // Total = 358 + 150 = 508
        self::assertSame(508.0, $settlement['totalAllExpenses']);
        // Surplus = 600 - 508 = 92
        self::assertSame(92.0, $settlement['surplus']);
        self::assertCount(2, $settlement['expenses']);
    }

    public function testGetSettlementShowsDeficit(): void
    {
        $trip = $this->createTrip();
        $this->service->addExpense($trip->id(), ['label' => 'Big Cost', 'amount' => 500.0]);

        $reg = new Registration(
            id: null,
            tripId: $trip->id(),
            roomCategory: 'DZ',
            receivedAt: '2026-01-01',
            comment: '',
            participants: [new Participant('Max Mustermann', new DateTimeImmutable('1990-01-01'))]
        );
        $reg = $reg->withBilling(new DateTimeImmutable(), 200.0, []);
        $this->regRepo->save($reg);

        $settlement = $this->service->getSettlement($trip->id());

        self::assertSame(200.0, $settlement['totalRevenue']);
        self::assertSame(500.0, $settlement['totalAdditionalExpenses']);
        self::assertSame(358.0, $settlement['totalPlannedCosts']);
        // Total = 358 + 500 = 858, surplus = 200 - 858 = -658
        self::assertSame(858.0, $settlement['totalAllExpenses']);
        self::assertSame(-658.0, $settlement['surplus']);
    }

    public function testGetSettlementPlannedCostItemsBreakdown(): void
    {
        $trip = $this->createTrip();

        $settlement = $this->service->getSettlement($trip->id());

        $items = $settlement['plannedCostItems'];
        // 3 booking categories with count>0 + spa tax = 4 items
        self::assertCount(4, $items);
        // Check labels contain category info
        self::assertStringContainsString('Zimmer:', $items[0]['label']);
        self::assertStringContainsString('Kurabgabe', $items[3]['label']);
    }

    public function testGetSettlementThrowsNotFoundForUnknownTrip(): void
    {
        $this->expectException(NotFoundException::class);
        $this->service->getSettlement(9999);
    }
}

final class InMemoryActualExpenseRepository implements ActualExpenseRepositoryInterface
{
    /** @var array<int, ActualExpense> */
    private array $items = [];
    private int $nextId = 1;

    public function save(ActualExpense $expense): ActualExpense
    {
        $id = $this->nextId++;
        $saved = $expense->withId($id);
        $this->items[$id] = $saved;
        return $saved;
    }

    public function update(ActualExpense $expense): ActualExpense
    {
        $id = $expense->id();
        if ($id === null) {
            throw new InvalidArgumentException('Expense id is required.');
        }
        $this->items[$id] = $expense;
        return $expense;
    }

    public function delete(int $id): void
    {
        unset($this->items[$id]);
    }

    /** @return ActualExpense[] */
    public function findByTripId(int $tripId): array
    {
        return array_values(array_filter($this->items, static fn ($e) => $e->tripId() === $tripId));
    }

    public function getById(int $id): ?ActualExpense
    {
        return $this->items[$id] ?? null;
    }

    public function deleteByTripId(int $tripId): void
    {
        $this->items = array_filter($this->items, static fn ($e) => $e->tripId() !== $tripId);
    }
}




