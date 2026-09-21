<?php

declare(strict_types=1);

namespace App\Domain\Service;

final class PerPersonDistributionStrategy implements ExpenseDistributionStrategy
{
    public function denominator(array $categories): int
    {
        $sum = 0;
        foreach ($categories as $category) {
            $sum += $category->count();
        }

        return $sum;
    }
}
