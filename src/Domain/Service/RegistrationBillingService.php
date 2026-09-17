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
    public const string CATEGORY_SPA_TAX = 'SPA_TAX';

    public const string CATEGORY_SPA_TAX_CREDIT = 'SPA_TAX_CREDIT';

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

        $policy = $trip->pricingPolicy();
        $spaTaxPerParticipant = $this->round2($policy->spaTaxPerPerson() * $trip->nights());

        $result = [];
        foreach ($registrations as $registration) {
            $items = [];
            $total = 0.0;

            foreach ($registration->participants() as $participant) {
                $categoryType = $this->determineCategory(
                    $participant,
                    $registration->roomCategory(),
                    $trip->startDate(),
                    $policy->adultAgeThreshold()
                );

                $price = $pricesPerCategory[$categoryType] ?? 0.0;
                $items[] = new BillingLineItem($participant->name(), $categoryType, $price);
                $total = $this->round2($total + $price);

                $correction = $this->spaTaxCorrection(
                    $participant->ageAtDate($trip->startDate()),
                    $categoryType,
                    $policy->spaTaxAgeThreshold(),
                    $spaTaxPerParticipant
                );

                if ($correction !== null) {
                    $items[] = new BillingLineItem($participant->name(), $correction[0], $correction[1]);
                    $total = $this->round2($total + $correction[1]);
                }
            }

            $result[] = $registration->withBilling($now, $total, $items);
        }

        return $result;
    }

    /**
     * The calculation prices the spa tax into the adult categories only. Once the actual ages are
     * known, participants who fall on the other side of the spa tax age get corrected here.
     *
     * @return array{0: string, 1: float}|null
     */
    private function spaTaxCorrection(
        int $age,
        string $categoryType,
        int $spaTaxAgeThreshold,
        float $spaTaxPerParticipant
    ): ?array {
        if ($spaTaxPerParticipant === 0.0) {
            return null;
        }

        $liable = $age >= $spaTaxAgeThreshold;
        $pricedIn = $categoryType !== 'CHILD';

        if ($liable && !$pricedIn) {
            return [self::CATEGORY_SPA_TAX, $spaTaxPerParticipant];
        }

        if (!$liable && $pricedIn) {
            return [self::CATEGORY_SPA_TAX_CREDIT, -$spaTaxPerParticipant];
        }

        return null;
    }

    public function determineCategory(
        Participant $participant,
        string $roomCategory,
        DateTimeImmutable $tripStartDate,
        int $adultAgeThreshold
    ): string {
        $age = $participant->ageAtDate($tripStartDate);

        if ($age < $adultAgeThreshold) {
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

