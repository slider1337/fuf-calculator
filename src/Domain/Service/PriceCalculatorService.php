<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Trip\DistributionMethod;
use App\Domain\Trip\RoomBooking;
use App\Domain\Trip\RoomCategoryType;
use App\Domain\Trip\Trip;

final class PriceCalculatorService
{
    public function calculate(Trip $trip): array
    {
        $policy = $trip->pricingPolicy();
        $bookings = $trip->bookings();
        $nights = $trip->nights();

        $categories = $this->pricingCategories($bookings, $policy->averageAdultPrice());

        $distribution = $this->distributionFor($policy->distributionMethod());
        $denominator = max(1, $distribution->denominator($categories));

        $totalGroupExpenses = 0.0;
        foreach ($trip->groupExpenses() as $expense) {
            $totalGroupExpenses = $this->round2($totalGroupExpenses + $expense->amount());
        }

        $sharedExpensePerUnit = $this->round2($totalGroupExpenses / $denominator);

        $pricesPerCategory = [];
        $priceBreakdowns = [];
        $totalParticipants = 0;
        $totalCalculatedRevenue = 0.0;

        foreach ($categories as $category) {
            if ($category->count() === 0) {
                continue;
            }

            $categoryExpenseShare = $policy->distributionMethod() === DistributionMethod::PER_PERSON
                ? $sharedExpensePerUnit
                : $this->round2($sharedExpensePerUnit / $category->count());

            $basePerNight = $category->basePricePerPerson();
            $spaTaxPerNight = $category->isSpaTaxLiable() ? $policy->spaTaxPerPerson() : 0.0;
            $baseTotalPerPerson = $this->round2($basePerNight * $nights);
            $spaTaxTotalPerPerson = $this->round2($spaTaxPerNight * $nights);
            $withSpaTax = $this->round2($baseTotalPerPerson + $spaTaxTotalPerPerson);
            $withExpenses = $this->round2($withSpaTax + $categoryExpenseShare);
            $markupAmount = $this->round2($withExpenses * $policy->markupPercent()->factor());
            $withMarkup = $this->round2($withExpenses + $markupAmount);
            $clubFeeAmount = $this->round2($withMarkup * $policy->clubFeePercent()->factor());
            $finalPrice = $this->round2($withMarkup + $clubFeeAmount);

            $salesPriceDefault = $this->roundToNearest5($finalPrice);
            $salesPrice = $category->salesPricePerPerson() ?? $salesPriceDefault;

            $categoryKey = $category->key();
            $pricesPerCategory[$categoryKey] = $salesPrice;

            $categoryRevenue = $this->round2($salesPrice * $category->count());

            $priceBreakdowns[$categoryKey] = [
                'basePricePerPerson' => $basePerNight,
                'spaTaxPerPerson' => $spaTaxPerNight,
                'nights' => $nights,
                'baseTotalPerPerson' => $baseTotalPerPerson,
                'spaTaxTotalPerPerson' => $spaTaxTotalPerPerson,
                'groupExpenseShare' => $categoryExpenseShare,
                'subtotalBeforeMarkup' => $withExpenses,
                'markupPercent' => $policy->markupPercent()->value(),
                'markupAmount' => $markupAmount,
                'subtotalBeforeClubFee' => $withMarkup,
                'clubFeePercent' => $policy->clubFeePercent()->value(),
                'clubFeeAmount' => $clubFeeAmount,
                'finalPrice' => $finalPrice,
                'salesPricePerPerson' => $salesPrice,
                'salesPriceDefault' => $salesPriceDefault,
                'salesPriceOverridden' => $category->salesPricePerPerson() !== null,
                'count' => $category->count(),
                'categoryRevenue' => $categoryRevenue,
            ];

            $totalCalculatedRevenue = $this->round2($totalCalculatedRevenue + $categoryRevenue);
            $totalParticipants += $category->count();
        }

        // Die Abrechnung ordnet jeden Teilnehmer einer echten Zimmerkategorie zu.
        // Beim einheitlichen Erwachsenenpreis gilt fuer beide derselbe Preis.
        if (isset($pricesPerCategory[PricingCategory::KEY_ADULT])) {
            $adultPrice = $pricesPerCategory[PricingCategory::KEY_ADULT];
            $pricesPerCategory[RoomCategoryType::ADULT_DOUBLE->value] = $adultPrice;
            $pricesPerCategory[RoomCategoryType::ADULT_MULTI->value] = $adultPrice;
        }

        // Kosten sind das, was die Unterkunft tatsaechlich kostet. Der gemittelte
        // Preis ist eine Frage der Preisbildung und aendert daran nichts.
        $costs = $this->lodgingCosts($bookings, $nights, $policy->spaTaxPerPerson());
        $totalCalculatedCosts = $this->round2(
            $costs['base'] + $costs['spaTax'] + $totalGroupExpenses
        );

        return [
            'pricesPerCategory' => $pricesPerCategory,
            'priceBreakdowns' => $priceBreakdowns,
            'totalGroupExpenses' => $totalGroupExpenses,
            'totalParticipants' => $totalParticipants,
            'totalCalculatedRevenue' => $totalCalculatedRevenue,
            'totalCalculatedCosts' => $totalCalculatedCosts,
            'surplus' => $this->round2($totalCalculatedRevenue - $totalCalculatedCosts),
            'distributionMethod' => $policy->distributionMethod()->value,
            'spaTaxAgeThreshold' => $policy->spaTaxAgeThreshold(),
            'adultAgeThreshold' => $policy->adultAgeThreshold(),
            'averageAdultPrice' => $policy->averageAdultPrice(),
            'startDate' => $trip->startDate()->format('Y-m-d'),
            'endDate' => $trip->endDate()?->format('Y-m-d'),
            'nights' => $nights,
        ];
    }

    /**
     * @param RoomBooking[] $bookings
     * @return PricingCategory[]
     */
    private function pricingCategories(array $bookings, bool $averageAdultPrice): array
    {
        if (!$averageAdultPrice) {
            return array_map(PricingCategory::fromBooking(...), $bookings);
        }

        $adultBookings = [];
        $categories = [];

        foreach ($bookings as $booking) {
            if ($booking->categoryType() === RoomCategoryType::CHILD) {
                $categories[] = PricingCategory::fromBooking($booking);
                continue;
            }

            $adultBookings[] = $booking;
        }

        return $adultBookings === []
            ? $categories
            : [PricingCategory::averagedAdults($adultBookings), ...$categories];
    }

    /**
     * @param RoomBooking[] $bookings
     * @return array{base: float, spaTax: float}
     */
    private function lodgingCosts(array $bookings, int $nights, float $spaTaxPerPerson): array
    {
        $base = 0.0;
        $spaTax = 0.0;

        foreach ($bookings as $booking) {
            $baseTotalPerPerson = $this->round2($booking->basePricePerPerson() * $nights);
            $base = $this->round2($base + $this->round2($baseTotalPerPerson * $booking->count()));

            $spaTaxPerNight = $booking->categoryType() === RoomCategoryType::CHILD
                ? 0.0
                : $spaTaxPerPerson;
            $spaTaxTotalPerPerson = $this->round2($spaTaxPerNight * $nights);
            $spaTax = $this->round2($spaTax + $this->round2($spaTaxTotalPerPerson * $booking->count()));
        }

        return ['base' => $base, 'spaTax' => $spaTax];
    }

    private function distributionFor(DistributionMethod $method): ExpenseDistributionStrategy
    {
        return match ($method) {
            DistributionMethod::PER_PERSON => new PerPersonDistributionStrategy(),
            DistributionMethod::PER_CATEGORY_UNITS => new PerCategoryUnitsDistributionStrategy(),
        };
    }

    private function round2(float $value): float
    {
        return round($value, 2, PHP_ROUND_HALF_UP);
    }

    private function roundToNearest5(float $value): float
    {
        return round($value / 5, 0, PHP_ROUND_HALF_UP) * 5;
    }
}
