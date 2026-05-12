<?php

declare(strict_types=1);

namespace Tests\Unit;

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

final class PriceCalculatorServiceTest extends TestCase
{
    public function testCalculatePerPersonReturnsCategoryPricesAndParticipants(): void
    {
        $trip = new Trip(
            null,
            'Winterfreizeit',
            new DateTimeImmutable('2026-01-10'),
            new TripPricingPolicy(
                new Percentage(10.0),
                new Percentage(5.0),
                DistributionMethod::PER_PERSON,
                2.50,
                18
            ),
            [
                new RoomBooking(RoomCategoryType::ADULT_DOUBLE, 4, 100.00),
                new RoomBooking(RoomCategoryType::ADULT_MULTI, 2, 90.00),
                new RoomBooking(RoomCategoryType::CHILD, 1, 60.00),
            ],
            [new GroupExpense('Getraenke', 70.00)],
            700.00,
            800.00,
            0,
            new DateTimeImmutable('2026-01-11')
        );

        $result = new PriceCalculatorService()->calculate($trip);

        self::assertSame('PER_PERSON', $result['distributionMethod']);
        self::assertSame(7, $result['totalParticipants']);
        self::assertArrayHasKey('ADULT_DOUBLE', $result['pricesPerCategory']);
        self::assertGreaterThan(0, $result['totalCalculatedRevenue']);

        // Verify breakdowns exist and have all fields
        self::assertArrayHasKey('priceBreakdowns', $result);
        self::assertArrayHasKey('ADULT_DOUBLE', $result['priceBreakdowns']);
        $bd = $result['priceBreakdowns']['ADULT_DOUBLE'];
        self::assertSame(100.0, $bd['basePricePerPerson']);
        self::assertSame(2.5, $bd['spaTaxPerPerson']);
        self::assertArrayHasKey('groupExpenseShare', $bd);
        self::assertArrayHasKey('subtotalBeforeMarkup', $bd);
        self::assertArrayHasKey('markupPercent', $bd);
        self::assertSame(10.0, $bd['markupPercent']);
        self::assertArrayHasKey('markupAmount', $bd);
        self::assertArrayHasKey('subtotalBeforeClubFee', $bd);
        self::assertArrayHasKey('clubFeePercent', $bd);
        self::assertSame(5.0, $bd['clubFeePercent']);
        self::assertArrayHasKey('clubFeeAmount', $bd);
        self::assertArrayHasKey('finalPrice', $bd);
        self::assertSame($result['pricesPerCategory']['ADULT_DOUBLE'], $bd['salesPricePerPerson']);
        self::assertSame(4, $bd['count']);
        self::assertSame($bd['salesPricePerPerson'] * 4, $bd['categoryRevenue']);
        self::assertArrayHasKey('totalGroupExpenses', $result);
        self::assertSame(70.0, $result['totalGroupExpenses']);
    }

    public function testCalculatePerCategoryUnitsUsesSelectedStrategy(): void
    {
        $trip = new Trip(
            null,
            'Sommerfreizeit',
            new DateTimeImmutable('2026-08-01'),
            new TripPricingPolicy(
                new Percentage(10.0),
                new Percentage(5.0),
                DistributionMethod::PER_CATEGORY_UNITS,
                1.00,
                18
            ),
            [
                new RoomBooking(RoomCategoryType::ADULT_DOUBLE, 2, 100.00),
                new RoomBooking(RoomCategoryType::ADULT_MULTI, 0, 90.00),
                new RoomBooking(RoomCategoryType::CHILD, 2, 50.00),
            ],
            [new GroupExpense('Lagerfeuer', 20.00)],
            300.00,
            350.00,
            0,
            new DateTimeImmutable('2026-08-02')
        );

        $result = new PriceCalculatorService()->calculate($trip);

        self::assertSame('PER_CATEGORY_UNITS', $result['distributionMethod']);
        self::assertSame('2026-08-01', $result['startDate']);
        self::assertArrayHasKey('CHILD', $result['pricesPerCategory']);

        // Verify breakdowns for PER_CATEGORY_UNITS
        self::assertArrayHasKey('priceBreakdowns', $result);
        self::assertArrayHasKey('CHILD', $result['priceBreakdowns']);
        self::assertArrayHasKey('ADULT_DOUBLE', $result['priceBreakdowns']);
        // ADULT_MULTI has count=0, should not be in breakdowns
        self::assertArrayNotHasKey('ADULT_MULTI', $result['priceBreakdowns']);

        $childBd = $result['priceBreakdowns']['CHILD'];
        self::assertSame(50.0, $childBd['basePricePerPerson']);
        self::assertSame(0.0, $childBd['spaTaxPerPerson']);
        self::assertSame(2, $childBd['count']);
        self::assertSame(1, $childBd['nights']);
        // Verify calculation chain: subtotal = baseTotal + spaTotal + expense share
        self::assertSame(
            $childBd['subtotalBeforeMarkup'],
            round($childBd['baseTotalPerPerson'] + $childBd['spaTaxTotalPerPerson'] + $childBd['groupExpenseShare'], 2, PHP_ROUND_HALF_UP)
        );
    }
}

