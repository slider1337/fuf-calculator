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

            $base = $booking->basePricePerPerson();
            $spaTax = $policy->spaTaxPerPerson();
            $withSpaTax = $this->round2($base + $spaTax);
            $withExpenses = $this->round2($withSpaTax + $categoryExpenseShare);
            $markupAmount = $this->round2($withExpenses * $policy->markupPercent()->factor());
            $withMarkup = $this->round2($withExpenses + $markupAmount);
            $clubFeeAmount = $this->round2($withMarkup * $policy->clubFeePercent()->factor());
            $finalPrice = $this->round2($withMarkup + $clubFeeAmount);

            $categoryKey = $booking->categoryType()->value;
            $pricesPerCategory[$categoryKey] = $finalPrice;

            $priceBreakdowns[$categoryKey] = [
                'basePricePerPerson' => $base,
                'spaTaxPerPerson' => $spaTax,
                'groupExpenseShare' => $categoryExpenseShare,
                'subtotalBeforeMarkup' => $withExpenses,
                'markupPercent' => $policy->markupPercent()->value(),
                'markupAmount' => $markupAmount,
                'subtotalBeforeClubFee' => $withMarkup,
                'clubFeePercent' => $policy->clubFeePercent()->value(),
                'clubFeeAmount' => $clubFeeAmount,
                'finalPrice' => $finalPrice,
                'count' => $booking->count(),
                'categoryRevenue' => $this->round2($finalPrice * $booking->count()),
            ];

            $categoryRevenue = $this->round2($finalPrice * $booking->count());
            $totalCalculatedRevenue = $this->round2($totalCalculatedRevenue + $categoryRevenue);

            $categoryBaseCost = $this->round2($booking->basePricePerPerson() * $booking->count());
            $totalBaseCosts = $this->round2($totalBaseCosts + $categoryBaseCost);

            $categorySpaTax = $this->round2($policy->spaTaxPerPerson() * $booking->count());
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
}

