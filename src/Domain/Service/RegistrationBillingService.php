<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Registration\BillingLineItem;
use App\Domain\Registration\Participant;
use App\Domain\Registration\Registration;
use App\Domain\Trip\Trip;
use DateTimeImmutable;

final readonly class RegistrationBillingService
{
    public function __construct(private PriceCalculatorService $calculator)
    {
    }

    /**
     * @param Registration[] $registrations
     * @return Registration[]
     */
    public function calculateBillings(Trip $trip, array $registrations): array
    {
        $calculationResult = $this->calculator->calculate($trip);
        $pricesPerCategory = $calculationResult['pricesPerCategory'] ?? [];
        $now = new DateTimeImmutable();

        $result = [];
        foreach ($registrations as $registration) {
            $items = [];
            $total = 0.0;

            foreach ($registration->participants() as $participant) {
                $categoryType = $this->determineCategory(
                    $participant,
                    $registration->roomCategory(),
                    $trip->startDate(),
                    $trip->pricingPolicy()->spaTaxAgeThreshold()
                );

                $price = $pricesPerCategory[$categoryType] ?? 0.0;
                $items[] = new BillingLineItem($participant->name(), $categoryType, $price);
                $total = $this->round2($total + $price);
            }

            $result[] = $registration->withBilling($now, $total, $items);
        }

        return $result;
    }

    public function determineCategory(
        Participant $participant,
        string $roomCategory,
        DateTimeImmutable $tripStartDate,
        int $ageThreshold
    ): string {
        $age = $participant->ageAtDate($tripStartDate);

        if ($age < $ageThreshold) {
            return 'CHILD';
        }

        return $this->isDoubleRoom($roomCategory) ? 'ADULT_DOUBLE' : 'ADULT_MULTI';
    }

    private function isDoubleRoom(string $roomCategory): bool
    {
        $normalized = mb_strtolower(trim($roomCategory));

        return str_contains($normalized, '2-bett')
            || str_contains($normalized, 'doppelzimmer')
            || str_contains($normalized, 'double');
    }

    private function round2(float $value): float
    {
        return round($value, 2, PHP_ROUND_HALF_UP);
    }
}

