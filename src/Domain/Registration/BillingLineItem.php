<?php

declare(strict_types=1);

namespace App\Domain\Registration;

final class BillingLineItem
{
    public function __construct(
        private string $participantName,
        private string $categoryType,
        private float $price
    ) {
    }

    public function participantName(): string
    {
        return $this->participantName;
    }

    public function categoryType(): string
    {
        return $this->categoryType;
    }

    public function price(): float
    {
        return round($this->price, 2, PHP_ROUND_HALF_UP);
    }
}

