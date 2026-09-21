<?php

declare(strict_types=1);

namespace App\Domain\Service;

interface ExpenseDistributionStrategy
{
    /** @param PricingCategory[] $categories */
    public function denominator(array $categories): int;
}
