<?php

declare(strict_types=1);

namespace App\Domain\Service;

final class PerCategoryUnitsDistributionStrategy implements ExpenseDistributionStrategy
{
    public function denominator(array $categories): int
    {
        return count(array_filter($categories, static fn ($category) => $category->count() > 0));
    }
}
