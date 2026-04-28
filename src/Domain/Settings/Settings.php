<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Domain\Shared\ValueObject\Percentage;
use App\Domain\Trip\DistributionMethod;

final class Settings
{
    public function __construct(
        private Percentage $defaultMarkupPercent,
        private Percentage $defaultClubFeePercent,
        private DistributionMethod $defaultDistributionMethod,
        private float $defaultSpaTaxPerPerson,
        private int $defaultSpaTaxAgeThreshold
    ) {
    }

    public function defaultMarkupPercent(): Percentage
    {
        return $this->defaultMarkupPercent;
    }

    public function defaultClubFeePercent(): Percentage
    {
        return $this->defaultClubFeePercent;
    }

    public function defaultDistributionMethod(): DistributionMethod
    {
        return $this->defaultDistributionMethod;
    }

    public function defaultSpaTaxPerPerson(): float
    {
        return round($this->defaultSpaTaxPerPerson, 2, PHP_ROUND_HALF_UP);
    }

    public function defaultSpaTaxAgeThreshold(): int
    {
        return $this->defaultSpaTaxAgeThreshold;
    }
}

