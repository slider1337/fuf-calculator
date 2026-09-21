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

    /**
     * 8 Erwachsene im Doppelzimmer zu 90 EUR und 22 im Mehrbettzimmer zu 80 EUR ergeben
     * (8*90 + 22*80) / 30 = 82,67 EUR pro Nacht fuer alle Erwachsenen.
     */
    public function testAverageAdultPriceMergesAdultCategoriesIntoWeightedAverage(): void
    {
        $trip = $this->tripWithAdults(true, DistributionMethod::PER_PERSON, []);

        $result = new PriceCalculatorService()->calculate($trip);

        self::assertArrayHasKey('ADULT', $result['priceBreakdowns']);
        self::assertArrayNotHasKey('ADULT_DOUBLE', $result['priceBreakdowns']);
        self::assertArrayNotHasKey('ADULT_MULTI', $result['priceBreakdowns']);

        $adult = $result['priceBreakdowns']['ADULT'];
        self::assertSame(82.67, $adult['basePricePerPerson']);
        self::assertSame(30, $adult['count']);
        self::assertSame(82.67, $adult['finalPrice']);
    }

    public function testAverageAdultPriceGivesBothAdultCategoriesTheSameSalesPrice(): void
    {
        $trip = $this->tripWithAdults(true, DistributionMethod::PER_PERSON, []);

        $result = new PriceCalculatorService()->calculate($trip);

        self::assertSame(85.0, $result['pricesPerCategory']['ADULT_DOUBLE']);
        self::assertSame(85.0, $result['pricesPerCategory']['ADULT_MULTI']);
    }

    /**
     * Der gemittelte Preis ist ein Verkaufspreis. Die Unterkunftskosten bleiben die
     * echten 8*90 + 22*80 = 2480 EUR und nicht 30 * 82,67 = 2480,10 EUR.
     */
    public function testAverageAdultPriceLeavesLodgingCostsAtTheRealBasePrices(): void
    {
        $result = new PriceCalculatorService()->calculate(
            $this->tripWithAdults(true, DistributionMethod::PER_PERSON, [])
        );
        $withoutAveraging = new PriceCalculatorService()->calculate(
            $this->tripWithAdults(false, DistributionMethod::PER_PERSON, [])
        );

        self::assertSame(2480.0, $result['totalCalculatedCosts']);
        self::assertSame($withoutAveraging['totalCalculatedCosts'], $result['totalCalculatedCosts']);
    }

    public function testAverageAdultPriceCountsAdultsAsOneUnitForPerCategoryDistribution(): void
    {
        $trip = $this->tripWithAdults(
            true,
            DistributionMethod::PER_CATEGORY_UNITS,
            [new GroupExpense('Bus', 300.00)],
            childCount: 10
        );

        $result = new PriceCalculatorService()->calculate($trip);

        // Nenner 2 statt 3: Erwachsene und Kinder. 300 / 2 = 150 je Einheit.
        self::assertSame(5.00, $result['priceBreakdowns']['ADULT']['groupExpenseShare']);
        self::assertSame(15.00, $result['priceBreakdowns']['CHILD']['groupExpenseShare']);
    }

    public function testAverageAdultPriceWithOnlyOneOccupiedAdultCategoryKeepsThatPrice(): void
    {
        $trip = $this->tripWithAdults(true, DistributionMethod::PER_PERSON, [], doubleCount: 0);

        $result = new PriceCalculatorService()->calculate($trip);

        $adult = $result['priceBreakdowns']['ADULT'];
        self::assertSame(80.00, $adult['basePricePerPerson']);
        self::assertSame(22, $adult['count']);
    }

    public function testAverageAdultPriceWithoutAdultsOmitsTheAdultBreakdown(): void
    {
        $trip = $this->tripWithAdults(
            true,
            DistributionMethod::PER_PERSON,
            [],
            doubleCount: 0,
            multiCount: 0,
            childCount: 4
        );

        $result = new PriceCalculatorService()->calculate($trip);

        self::assertArrayNotHasKey('ADULT', $result['priceBreakdowns']);
        self::assertArrayHasKey('CHILD', $result['priceBreakdowns']);
        self::assertSame(4, $result['totalParticipants']);
    }

    public function testAverageAdultPriceOffKeepsSeparateAdultBreakdowns(): void
    {
        $trip = $this->tripWithAdults(false, DistributionMethod::PER_PERSON, []);

        $result = new PriceCalculatorService()->calculate($trip);

        self::assertArrayNotHasKey('ADULT', $result['priceBreakdowns']);
        self::assertSame(90.0, $result['priceBreakdowns']['ADULT_DOUBLE']['basePricePerPerson']);
        self::assertSame(80.0, $result['priceBreakdowns']['ADULT_MULTI']['basePricePerPerson']);
    }

    public function testAverageAdultPriceExposesTheCategoriesItWasAveragedFrom(): void
    {
        $trip = $this->tripWithAdults(true, DistributionMethod::PER_PERSON, []);

        $result = new PriceCalculatorService()->calculate($trip);

        self::assertSame(
            [
                ['categoryType' => 'ADULT_DOUBLE', 'count' => 8, 'basePricePerPerson' => 90.0, 'total' => 720.0],
                ['categoryType' => 'ADULT_MULTI', 'count' => 22, 'basePricePerPerson' => 80.0, 'total' => 1760.0],
            ],
            $result['priceBreakdowns']['ADULT']['averagedFrom']
        );
    }

    public function testAverageAdultPriceOmitsTheDerivationWhenOnlyOneCategoryContributes(): void
    {
        $trip = $this->tripWithAdults(true, DistributionMethod::PER_PERSON, [], doubleCount: 0);

        $result = new PriceCalculatorService()->calculate($trip);

        self::assertArrayNotHasKey('averagedFrom', $result['priceBreakdowns']['ADULT']);
    }

    public function testBreakdownsCarryNoDerivationWhenAveragingIsOff(): void
    {
        $trip = $this->tripWithAdults(false, DistributionMethod::PER_PERSON, []);

        $result = new PriceCalculatorService()->calculate($trip);

        self::assertArrayNotHasKey('averagedFrom', $result['priceBreakdowns']['ADULT_DOUBLE']);
        self::assertArrayNotHasKey('averagedFrom', $result['priceBreakdowns']['ADULT_MULTI']);
    }

    /** @param GroupExpense[] $groupExpenses */
    private function tripWithAdults(
        bool $averageAdultPrice,
        DistributionMethod $method,
        array $groupExpenses,
        int $doubleCount = 8,
        int $multiCount = 22,
        int $childCount = 0
    ): Trip {
        return new Trip(
            null,
            'Sommerlager',
            new DateTimeImmutable('2026-07-01'),
            new TripPricingPolicy(
                new Percentage(0.0),
                new Percentage(0.0),
                $method,
                0.0,
                18,
                16,
                $averageAdultPrice
            ),
            [
                new RoomBooking(RoomCategoryType::ADULT_DOUBLE, $doubleCount, 90.00),
                new RoomBooking(RoomCategoryType::ADULT_MULTI, $multiCount, 80.00),
                new RoomBooking(RoomCategoryType::CHILD, $childCount, 50.00),
            ],
            $groupExpenses,
            0.0,
            0.0,
            0,
            new DateTimeImmutable('2026-07-02')
        );
    }
}
