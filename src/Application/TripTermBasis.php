<?php

declare(strict_types=1);
namespace App\Application;
use App\Domain\Form\Decimal;

use App\Domain\Trip\Trip;
use App\Domain\Service\PriceCalculatorService;

final class TripTermBasis
{
    /** @return list<array{label:string,category:'ADULT_DOUBLE'|'ADULT_MULTI'|'CHILD'|'GUEST',price:string}> */
    public static function prices(Trip $trip): array
    {
        $calculated=(new PriceCalculatorService())->calculate($trip)['pricesPerCategory'];$result=[];
        foreach(['ADULT_DOUBLE'=>'Erwachsene · Doppelzimmer','ADULT_MULTI'=>'Erwachsene · Mehrbettzimmer','CHILD'=>'Kinder'] as $key=>$label){
            if(isset($calculated[$key]))$result[]=['label'=>$label,'category'=>$key,'price'=>Decimal::normalize(number_format($calculated[$key],2,'.',''))??throw new \LogicException('Invalid calculated price')];
        }
        return $result;
    }

    /** @param list<array{label:string,category:'ADULT_DOUBLE'|'ADULT_MULTI'|'CHILD'|'GUEST',price:?string}> $extras
     * @return list<array{label:string,category:'ADULT_DOUBLE'|'ADULT_MULTI'|'CHILD'|'GUEST',price:?string}> */
    public static function groups(Trip $trip,array $extras): array
    {
        $base=self::prices($trip);$categories=array_column($base,'category');$labels=array_map('mb_strtolower',array_column($base,'label'));
        return [...$base,...array_values(array_filter($extras,static fn($g)=>!in_array($g['category'],$categories,true)&&!in_array(mb_strtolower($g['label']),$labels,true)))];
    }

    public static function description(Trip $trip,string $description): string
    {
        $start='<!-- fuf-trip-rooms -->';$end='<!-- /fuf-trip-rooms -->';
        $description=trim(preg_replace('/\\[Zimmerkategorien aus der Reise\\].*?\\[\/Zimmerkategorien\\]/s','',$description)??$description);
        $rooms=BookingRooms::categories($trip);
        return $rooms===[]?$description:trim($description."\n\n".$start."\nZimmerkategorien:\n".implode("\n",$rooms)."\n".$end);
    }
}
