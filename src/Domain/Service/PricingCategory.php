<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Trip\RoomBooking;
use App\Domain\Trip\RoomCategoryType;

/**
 * Eine Kategorie, wie die Preiskette sie sieht. Im Normalfall ist das genau eine
 * Buchungszeile. Bei einheitlichem Erwachsenenpreis fallen Doppel- und
 * Mehrbettzimmer zu einer Kategorie mit gewichtetem Durchschnittspreis zusammen -
 * nur so bekommen beide auch bei Verteilung pro Kategorieeinheit denselben Preis.
 */
final readonly class PricingCategory
{
    public const string KEY_ADULT = 'ADULT';

    private function __construct(
        private string $key,
        private int $count,
        private float $basePricePerPerson,
        private ?float $salesPricePerPerson,
        private bool $spaTaxLiable
    ) {
    }

    public static function fromBooking(RoomBooking $booking): self
    {
        return new self(
            $booking->categoryType()->value,
            $booking->count(),
            $booking->basePricePerPerson(),
            $booking->salesPricePerPerson(),
            $booking->categoryType() !== RoomCategoryType::CHILD
        );
    }

    /**
     * Gewichteter Durchschnitt der Naechtigungspreise, etwa (8*90 + 22*80) / 30 = 82,67.
     *
     * @param RoomBooking[] $adultBookings
     */
    public static function averagedAdults(array $adultBookings): self
    {
        $count = 0;
        $weightedSum = 0.0;
        $salesPricePerPerson = null;

        foreach ($adultBookings as $booking) {
            $count += $booking->count();
            $weightedSum += $booking->basePricePerPerson() * $booking->count();
            // Der Verkaufspreis wird auf beiden Zeilen gleich gespeichert; die erste
            // gesetzte gewinnt.
            $salesPricePerPerson ??= $booking->salesPricePerPerson();
        }

        $average = $count === 0
            ? 0.0
            : round($weightedSum / $count, 2, PHP_ROUND_HALF_UP);

        return new self(self::KEY_ADULT, $count, $average, $salesPricePerPerson, true);
    }

    public function key(): string
    {
        return $this->key;
    }

    public function count(): int
    {
        return $this->count;
    }

    public function basePricePerPerson(): float
    {
        return $this->basePricePerPerson;
    }

    public function salesPricePerPerson(): ?float
    {
        return $this->salesPricePerPerson;
    }

    public function isSpaTaxLiable(): bool
    {
        return $this->spaTaxLiable;
    }
}
