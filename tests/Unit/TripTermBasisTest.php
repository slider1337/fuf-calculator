<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\TripTermBasis;
use App\Domain\Shared\ValueObject\Percentage;
use App\Domain\Trip\{DistributionMethod,RoomBooking,RoomCategoryType,Trip,TripPricingPolicy};
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class TripTermBasisTest extends TestCase
{
    private function trip(bool $average, float $double = 90, float $multi = 80, int $doubleCount = 2, int $multiCount = 2): Trip
    {
        return new Trip(null,'Test',new DateTimeImmutable('2026-11-01'),
            new TripPricingPolicy(new Percentage(0),new Percentage(0),DistributionMethod::PER_PERSON,0,18,16,$average),
            [new RoomBooking(RoomCategoryType::ADULT_DOUBLE,$doubleCount,$double),new RoomBooking(RoomCategoryType::ADULT_MULTI,$multiCount,$multi),new RoomBooking(RoomCategoryType::CHILD,1,40)],[],0,0,
            endDate:new DateTimeImmutable('2026-11-02'));
    }

    public function testGeneratedRoomDescriptionIsIdempotentAndKeepsOwnHtml():void
    {
        $original=$this->trip(true);
        $trip=new Trip(null,'HTML',new DateTimeImmutable('2026-11-01'),$original->pricingPolicy(),$original->bookings(),[],0,0,roomReservations:[new \App\Domain\Trip\RoomReservation('2-Bettzimmer',2)]);
        $first=TripTermBasis::description($trip,'<p>Eigener Text</p>');
        self::assertSame($first,TripTermBasis::description($trip,$first));
        self::assertSame(1,substr_count($first,'Zimmerkategorien:'));
        self::assertStringContainsString('<p>Eigener Text</p>',$first);
        self::assertSame('<p>Eigener Text</p>',TripTermBasis::description($original,$first));
    }

    public function testAverageAdultPriceProducesOneAdultGroupAndKeepsChildrenAndGuests(): void
    {
        $guest=['label'=>'Gast','category'=>'GUEST','price'=>'110'];
        $groups=TripTermBasis::groups($this->trip(true),[
            ['label'=>'Alter DZ-Preis','category'=>'ADULT_DOUBLE','price'=>'90'],
            ['label'=>'Alter Mehrbettpreis','category'=>'ADULT_MULTI','price'=>'80'],$guest,
        ]);
        self::assertSame([
            ['label'=>'Erwachsener','category'=>'ADULT_DOUBLE','price'=>'85'],
            ['label'=>'Kinder','category'=>'CHILD','price'=>'40'],$guest,
        ],$groups);
    }

    public function testEqualSellingPricesAlsoProduceOneAdultGroup(): void
    {
        self::assertSame(['Erwachsener','Kinder'],array_column(TripTermBasis::prices($this->trip(false,80,80)),'label'));
    }

    public function testDifferentAdultPricesKeepRoomSpecificGroups(): void
    {
        self::assertSame(['Erwachsene · Doppelzimmer','Erwachsene · Mehrbettzimmer','Kinder'],array_column(TripTermBasis::prices($this->trip(false)),'label'));
    }

    public function testOnlyOneAdultCategoryUsesSimpleAdultName(): void
    {
        self::assertSame(['label'=>'Erwachsener','category'=>'ADULT_DOUBLE','price'=>'80'],TripTermBasis::prices($this->trip(true,doubleCount:0))[0]);
    }

    public function testNoAdultsDoesNotCreateAnAdultPriceGroup(): void
    {
        self::assertSame([['label'=>'Kinder','category'=>'CHILD','price'=>'40']],TripTermBasis::prices($this->trip(true,doubleCount:0,multiCount:0)));
    }
}
