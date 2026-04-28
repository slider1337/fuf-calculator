<?php

declare(strict_types=1);

namespace App\Domain\Trip;

use App\Domain\Shared\ValueObject\Percentage;

final class TripPricingPolicy
{
    public function __construct(
        private Percentage $markupPercent,
        private Percentage $clubFeePercent,
        private DistributionMethod $distributionMethod,
        private float $spaTaxPerPerson,
        private int $spaTaxAgeThreshold
    ) {
    }

    public function markupPercent(): Percentage
    {
        return $this->markupPercent;
    }

    public function clubFeePercent(): Percentage
    {
        return $this->clubFeePercent;
    }

    public function distributionMethod(): DistributionMethod
    {
        return $this->distributionMethod;
    }

    public function spaTaxPerPerson(): float
    {
        return round($this->spaTaxPerPerson, 2, PHP_ROUND_HALF_UP);
    }

    public function spaTaxAgeThreshold(): int
    {
        return $this->spaTaxAgeThreshold;
    }
}

