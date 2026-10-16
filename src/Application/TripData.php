<?php

declare(strict_types=1);
namespace App\Application;
/** @phpstan-type TripView array{id:?int,name:string,startDate:string,endDate:?string,markupPercent:float,clubFeePercent:float,distributionMethod:string,spaTaxPerPerson:float,spaTaxAgeThreshold:int,adultAgeThreshold:int,averageAdultPrice:bool,spaTaxCount:int,bookings:array<array{categoryType:string,count:int,basePricePerPerson:float,salesPricePerPerson:?float}>,groupExpenses:array<array{label:string,amount:float}>,roomReservations:array<array{roomType:string,count:int}>} */
final class TripData
{
    /** @return TripView */
    public static function fromTrip(\App\Domain\Trip\Trip $trip): array
    {
        return [
            'id' => $trip->id(),
            'name' => $trip->name(),
            'startDate' => $trip->startDate()->format('Y-m-d'),
            'endDate' => $trip->endDate()?->format('Y-m-d'),
            'markupPercent' => $trip->pricingPolicy()->markupPercent()->value(),
            'clubFeePercent' => $trip->pricingPolicy()->clubFeePercent()->value(),
            'distributionMethod' => $trip->pricingPolicy()->distributionMethod()->value,
            'spaTaxPerPerson' => $trip->pricingPolicy()->spaTaxPerPerson(),
            'spaTaxAgeThreshold' => $trip->pricingPolicy()->spaTaxAgeThreshold(),
            'adultAgeThreshold' => $trip->pricingPolicy()->adultAgeThreshold(),
            'averageAdultPrice' => $trip->pricingPolicy()->averageAdultPrice(),
            'spaTaxCount' => $trip->spaTaxCount(),
            'bookings' => array_map(static fn ($booking) => [
                'categoryType' => $booking->categoryType()->value,
                'count' => $booking->count(),
                'basePricePerPerson' => $booking->basePricePerPerson(),
                'salesPricePerPerson' => $booking->salesPricePerPerson(),
            ], $trip->bookings()),
            'groupExpenses' => array_map(static fn ($expense) => [
                'label' => $expense->label(),
                'amount' => $expense->amount(),
            ], $trip->groupExpenses()),
            'roomReservations' => array_map(static fn ($reservation) => [
                'roomType' => $reservation->roomType(),
                'count' => $reservation->count(),
            ], $trip->roomReservations()),
        ];
    }
}
