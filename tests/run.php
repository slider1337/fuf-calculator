<?php

declare(strict_types=1);

use App\Domain\Service\PriceCalculatorService;
use App\Domain\Shared\ValueObject\Percentage;
use App\Domain\Trip\DistributionMethod;
use App\Domain\Trip\GroupExpense;
use App\Domain\Trip\RoomBooking;
use App\Domain\Trip\RoomCategoryType;
use App\Domain\Trip\Trip;
use App\Domain\Trip\TripPricingPolicy;
use App\Infrastructure\Persistence\SqliteConnection;
use App\Infrastructure\Persistence\SqliteSchema;
use App\Infrastructure\Persistence\SqliteTripRepository;

require __DIR__ . '/bootstrap.php';

$tests = [];

$tests['calculator_per_person'] = static function (): void {
    $trip = new Trip(
        id: null,
        name: 'Winterfreizeit',
        startDate: new DateTimeImmutable('2026-01-10'),
        pricingPolicy: new TripPricingPolicy(
            new Percentage(10.0),
            new Percentage(5.0),
            DistributionMethod::PER_PERSON,
            2.50,
            18
        ),
        bookings: [
            new RoomBooking(RoomCategoryType::ADULT_DOUBLE, 4, 100.00),
            new RoomBooking(RoomCategoryType::ADULT_MULTI, 2, 90.00),
            new RoomBooking(RoomCategoryType::CHILD, 1, 60.00),
        ],
        groupExpenses: [
            new GroupExpense('Getraenke', 70.00),
        ],
        plannedTotalCosts: 700.00,
        plannedTotalRevenue: 800.00
    );

    $result = (new PriceCalculatorService())->calculate($trip);

    assertEquals('PER_PERSON', $result['distributionMethod'], 'distribution method mismatch');
    assertTrue($result['totalCalculatedRevenue'] > 0, 'revenue should be positive');
    assertEquals(7, $result['totalParticipants'], 'participant count mismatch');
};

$tests['calculator_per_category_units'] = static function (): void {
    $trip = new Trip(
        id: null,
        name: 'Sommerfreizeit',
        startDate: new DateTimeImmutable('2026-08-01'),
        pricingPolicy: new TripPricingPolicy(
            new Percentage(10.0),
            new Percentage(5.0),
            DistributionMethod::PER_CATEGORY_UNITS,
            1.00,
            18
        ),
        bookings: [
            new RoomBooking(RoomCategoryType::ADULT_DOUBLE, 2, 100.00),
            new RoomBooking(RoomCategoryType::ADULT_MULTI, 0, 90.00),
            new RoomBooking(RoomCategoryType::CHILD, 2, 50.00),
        ],
        groupExpenses: [
            new GroupExpense('Lagerfeuer', 20.00),
        ],
        plannedTotalCosts: 300.00,
        plannedTotalRevenue: 350.00
    );

    $result = (new PriceCalculatorService())->calculate($trip);

    assertEquals('PER_CATEGORY_UNITS', $result['distributionMethod'], 'distribution method mismatch');
    assertTrue(isset($result['pricesPerCategory']['ADULT_DOUBLE']), 'adult double price missing');
};

$tests['sqlite_roundtrip_trip'] = static function (): void {
    $databasePath = __DIR__ . '/../database/test.sqlite';
    if (file_exists($databasePath)) {
        unlink($databasePath);
    }

    $pdo = SqliteConnection::create($databasePath);
    SqliteSchema::ensure($pdo, __DIR__ . '/../database/schema.sql');

    $repository = new SqliteTripRepository($pdo);

    $saved = $repository->save(new Trip(
        id: null,
        name: 'Repo Test',
        startDate: new DateTimeImmutable('2026-02-05'),
        pricingPolicy: new TripPricingPolicy(
            new Percentage(10.0),
            new Percentage(5.0),
            DistributionMethod::PER_PERSON,
            1.5,
            18
        ),
        bookings: [
            new RoomBooking(RoomCategoryType::ADULT_DOUBLE, 1, 111.11),
            new RoomBooking(RoomCategoryType::ADULT_MULTI, 1, 99.99),
            new RoomBooking(RoomCategoryType::CHILD, 1, 50.00),
        ],
        groupExpenses: [new GroupExpense('Snack', 12.00)],
        plannedTotalCosts: 123.00,
        plannedTotalRevenue: 150.00
    ));

    assertTrue($saved->id() !== null, 'trip id should be assigned');

    $loaded = $repository->getById((int) $saved->id());
    assertTrue($loaded !== null, 'loaded trip should not be null');
    assertEquals('Repo Test', $loaded->name(), 'trip name mismatch');
};

$failed = 0;
$passed = 0;

foreach ($tests as $name => $test) {
    try {
        $test();
        $passed++;
        echo "[PASS] {$name}\n";
    } catch (Throwable $exception) {
        $failed++;
        echo "[FAIL] {$name}: {$exception->getMessage()}\n";
    }
}

echo "\nPassed: {$passed}, Failed: {$failed}\n";
exit($failed > 0 ? 1 : 0);

