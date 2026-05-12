<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Trip\DistributionMethod;
use App\Domain\Trip\RoomCategoryType;
use App\Domain\Trip\Trip;

final class PriceCalculatorService
{
    public function calculate(Trip $trip): array
    {
        $policy = $trip->pricingPolicy();
        $bookings = $trip->bookings();
        $nights = $trip->nights();

        $distribution = $this->distributionFor($policy->distributionMethod());
        $denominator = max(1, $distribution->denominator($bookings));

        $totalGroupExpenses = 0.0;
        foreach ($trip->groupExpenses() as $expense) {
            $totalGroupExpenses = $this->round2($totalGroupExpenses + $expense->amount());
        }

        $sharedExpensePerUnit = $this->round2($totalGroupExpenses / $denominator);

        $pricesPerCategory = [];
        $priceBreakdowns = [];
        $totalParticipants = 0;
        $totalCalculatedRevenue = 0.0;
        $totalBaseCosts = 0.0;
        $totalSpaTaxCosts = 0.0;

        foreach ($bookings as $booking) {
            if ($booking->count() === 0) {
                continue;
            }

            $categoryExpenseShare = $policy->distributionMethod() === DistributionMethod::PER_PERSON
                ? $sharedExpensePerUnit
                : $this->round2($sharedExpensePerUnit / $booking->count());

            $basePerNight = $booking->basePricePerPerson();
            $spaTaxPerNight = $booking->categoryType() === RoomCategoryType::CHILD
                ? 0.0
                : $policy->spaTaxPerPerson();
            $baseTotalPerPerson = $this->round2($basePerNight * $nights);
            $spaTaxTotalPerPerson = $this->round2($spaTaxPerNight * $nights);
            $withSpaTax = $this->round2($baseTotalPerPerson + $spaTaxTotalPerPerson);
            $withExpenses = $this->round2($withSpaTax + $categoryExpenseShare);
            $markupAmount = $this->round2($withExpenses * $policy->markupPercent()->factor());
            $withMarkup = $this->round2($withExpenses + $markupAmount);
            $clubFeeAmount = $this->round2($withMarkup * $policy->clubFeePercent()->factor());
            $finalPrice = $this->round2($withMarkup + $clubFeeAmount);

            $salesPriceDefault = $this->roundToNearest5($finalPrice);
            $salesPrice = $booking->salesPricePerPerson() ?? $salesPriceDefault;

            $categoryKey = $booking->categoryType()->value;
            $pricesPerCategory[$categoryKey] = $salesPrice;

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
                'salesPriceOverridden' => $booking->salesPricePerPerson() !== null,
                'count' => $booking->count(),
                'categoryRevenue' => $this->round2($salesPrice * $booking->count()),
            ];

            $categoryRevenue = $this->round2($salesPrice * $booking->count());
            $totalCalculatedRevenue = $this->round2($totalCalculatedRevenue + $categoryRevenue);

            $categoryBaseCost = $this->round2($baseTotalPerPerson * $booking->count());
            $totalBaseCosts = $this->round2($totalBaseCosts + $categoryBaseCost);

            $categorySpaTax = $this->round2($spaTaxTotalPerPerson * $booking->count());
            $totalSpaTaxCosts = $this->round2($totalSpaTaxCosts + $categorySpaTax);

            $totalParticipants += $booking->count();
        }

        $totalCalculatedCosts = $this->round2($totalBaseCosts + $totalSpaTaxCosts + $totalGroupExpenses);

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
            'startDate' => $trip->startDate()->format('Y-m-d'),
            'endDate' => $trip->endDate()?->format('Y-m-d'),
            'nights' => $nights,
        ];
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

