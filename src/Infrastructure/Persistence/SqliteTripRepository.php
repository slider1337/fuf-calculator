<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\TripRepositoryInterface;
use App\Domain\Shared\ValueObject\Percentage;
use App\Domain\Trip\DistributionMethod;
use App\Domain\Trip\GroupExpense;
use App\Domain\Trip\RoomBooking;
use App\Domain\Trip\RoomCategoryType;
use App\Domain\Trip\Trip;
use App\Domain\Trip\TripPricingPolicy;
use DateMalformedStringException;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;

final readonly class SqliteTripRepository implements TripRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function save(Trip $trip): Trip
    {
        $this->pdo->beginTransaction();

        $stmt = $this->pdo->prepare(
            'INSERT INTO trips (
                name, start_date, end_date, markup_percent, club_fee_percent, distribution_method, spa_tax_count,
                spa_tax_per_person, spa_tax_age_threshold, planned_total_costs, planned_total_revenue
            ) VALUES (:name, :startDate, :endDate, :markup, :club, :method, :spaTaxCount, :spaTax, :spaAge, :costs, :revenue)'
        );

        $policy = $trip->pricingPolicy();

        $stmt->execute([
            ':name' => $trip->name(),
            ':startDate' => $trip->startDate()->format('Y-m-d'),
            ':endDate' => $trip->endDate()?->format('Y-m-d'),
            ':markup' => $policy->markupPercent()->value(),
            ':club' => $policy->clubFeePercent()->value(),
            ':method' => $policy->distributionMethod()->value,
            ':spaTax' => $policy->spaTaxPerPerson(),
            ':spaTaxCount' => $trip->spaTaxCount(),
            ':spaAge' => $policy->spaTaxAgeThreshold(),
            ':costs' => $trip->plannedTotalCosts(),
            ':revenue' => $trip->plannedTotalRevenue(),
        ]);

        $tripId = (int) $this->pdo->lastInsertId();

        $this->persistChildren($tripId, $trip);

        $this->pdo->commit();

        return $trip->withId($tripId);
    }

    public function update(Trip $trip): Trip
    {
        $tripId = $trip->id();
        if ($tripId === null) {
            throw new InvalidArgumentException('Trip id is required for update.');
        }

        $this->pdo->beginTransaction();

        $stmt = $this->pdo->prepare(
            'UPDATE trips SET
                name = :name,
                start_date = :startDate,
                end_date = :endDate,
                markup_percent = :markup,
                club_fee_percent = :club,
                distribution_method = :method,
                spa_tax_per_person = :spaTax,
                spa_tax_count = :spaTaxCount,
                spa_tax_age_threshold = :spaAge,
                planned_total_costs = :costs,
                planned_total_revenue = :revenue
            WHERE id = :id'
        );

        $policy = $trip->pricingPolicy();

        $stmt->execute([
            ':id' => $tripId,
            ':name' => $trip->name(),
            ':startDate' => $trip->startDate()->format('Y-m-d'),
            ':endDate' => $trip->endDate()?->format('Y-m-d'),
            ':markup' => $policy->markupPercent()->value(),
            ':club' => $policy->clubFeePercent()->value(),
            ':method' => $policy->distributionMethod()->value,
            ':spaTax' => $policy->spaTaxPerPerson(),
            ':spaTaxCount' => $trip->spaTaxCount(),
            ':spaAge' => $policy->spaTaxAgeThreshold(),
            ':costs' => $trip->plannedTotalCosts(),
            ':revenue' => $trip->plannedTotalRevenue(),
        ]);

        $this->pdo->prepare('DELETE FROM trip_bookings WHERE trip_id = :tripId')->execute([':tripId' => $tripId]);
        $this->pdo->prepare('DELETE FROM trip_group_expenses WHERE trip_id = :tripId')->execute([':tripId' => $tripId]);

        $this->persistChildren($tripId, $trip);

        $this->pdo->commit();

        return $trip;
    }

    /**
     * @throws DateMalformedStringException
     */
    public function getById(int $id): ?Trip
    {
        $tripRow = $this->pdo->prepare('SELECT * FROM trips WHERE id = :id');
        $tripRow->execute([':id' => $id]);
        $trip = $tripRow->fetch();

        if ($trip === false) {
            return null;
        }

        $bookingsStmt = $this->pdo->prepare('SELECT * FROM trip_bookings WHERE trip_id = :tripId ORDER BY id ASC');
        $bookingsStmt->execute([':tripId' => $id]);
        $bookingsRows = $bookingsStmt->fetchAll();

        $bookings = [];
        foreach ($bookingsRows as $row) {
            $bookings[] = new RoomBooking(
                RoomCategoryType::from((string) $row['category_type']),
                (int) $row['participant_count'],
                (float) $row['base_price_per_person'],
                isset($row['sales_price_per_person']) && $row['sales_price_per_person'] !== null
                    ? (float) $row['sales_price_per_person']
                    : null
            );
        }

        $expensesStmt = $this->pdo->prepare('SELECT * FROM trip_group_expenses WHERE trip_id = :tripId ORDER BY id ASC');
        $expensesStmt->execute([':tripId' => $id]);
        $expensesRows = $expensesStmt->fetchAll();

        $expenses = [];
        foreach ($expensesRows as $row) {
            $expenses[] = new GroupExpense(
                (string) $row['label'],
                (float) $row['amount']
            );
        }

        return new Trip(
            id: (int) $trip['id'],
            name: (string) $trip['name'],
            startDate: new DateTimeImmutable((string) $trip['start_date']),
            pricingPolicy: new TripPricingPolicy(
                new Percentage((float) $trip['markup_percent']),
                new Percentage((float) $trip['club_fee_percent']),
                DistributionMethod::from((string) $trip['distribution_method']),
                (float) $trip['spa_tax_per_person'],
                (int) $trip['spa_tax_age_threshold']
            ),
            bookings: $bookings,
            groupExpenses: $expenses,
            plannedTotalCosts: (float) $trip['planned_total_costs'],
            plannedTotalRevenue: (float) $trip['planned_total_revenue'],
            spaTaxCount: (int) ($trip['spa_tax_count'] ?? 0),
            endDate: isset($trip['end_date']) && $trip['end_date'] !== null
                ? new DateTimeImmutable((string) $trip['end_date'])
                : null
        );
    }

    public function findAll(): array
    {
        $rows = $this->pdo->query('SELECT id, name, start_date FROM trips ORDER BY start_date DESC, id DESC')->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'startDate' => (string) $row['start_date'],
            ];
        }

        return $result;
    }

    private function persistChildren(int $tripId, Trip $trip): void
    {
        $bookingStmt = $this->pdo->prepare(
            'INSERT INTO trip_bookings (trip_id, category_type, participant_count, base_price_per_person, sales_price_per_person)
             VALUES (:tripId, :categoryType, :count, :basePrice, :salesPrice)'
        );

        foreach ($trip->bookings() as $booking) {
            $bookingStmt->execute([
                ':tripId' => $tripId,
                ':categoryType' => $booking->categoryType()->value,
                ':count' => $booking->count(),
                ':basePrice' => $booking->basePricePerPerson(),
                ':salesPrice' => $booking->salesPricePerPerson(),
            ]);
        }

        $expenseStmt = $this->pdo->prepare(
            'INSERT INTO trip_group_expenses (trip_id, label, amount)
             VALUES (:tripId, :label, :amount)'
        );

        foreach ($trip->groupExpenses() as $expense) {
            $expenseStmt->execute([
                ':tripId' => $tripId,
                ':label' => $expense->label(),
                ':amount' => $expense->amount(),
            ]);
        }
    }
}


