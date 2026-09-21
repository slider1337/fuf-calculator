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

    /** @param list<array{categoryType: string, count: int, basePricePerPerson: float, total: float}> $averagedFrom */
    private function __construct(
        private string $key,
        private int $count,
        private float $basePricePerPerson,
        private ?float $salesPricePerPerson,
        private bool $spaTaxLiable,
        private array $averagedFrom = []
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
        $parts = [];

        foreach ($adultBookings as $booking) {
            $count += $booking->count();
            $weightedSum += $booking->basePricePerPerson() * $booking->count();
            // Der Verkaufspreis wird auf beiden Zeilen gleich gespeichert; die erste
            // gesetzte gewinnt.
            $salesPricePerPerson ??= $booking->salesPricePerPerson();

            if ($booking->count() > 0) {
                $parts[] = [
                    'categoryType' => $booking->categoryType()->value,
                    'count' => $booking->count(),
                    'basePricePerPerson' => $booking->basePricePerPerson(),
                    'total' => round($booking->basePricePerPerson() * $booking->count(), 2, PHP_ROUND_HALF_UP),
                ];
            }
        }

        $average = $count === 0
            ? 0.0
            : round($weightedSum / $count, 2, PHP_ROUND_HALF_UP);

        // Ein einzelner Posten ist kein Durchschnitt - dann gibt es nichts herzuleiten.
        return new self(
            self::KEY_ADULT,
            $count,
            $average,
            $salesPricePerPerson,
            true,
            count($parts) > 1 ? $parts : []
        );
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

    /**
     * Die Posten, aus denen der Preis gemittelt wurde - leer, wenn nicht gemittelt
     * wurde oder nur ein Posten beigetragen hat.
     *
     * @return list<array{categoryType: string, count: int, basePricePerPerson: float, total: float}>
     */
    public function averagedFrom(): array
    {
        return $this->averagedFrom;
    }
}
