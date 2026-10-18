<?php

declare(strict_types=1);
namespace App\Application;

use App\Domain\Trip\Trip;

/** Room requests belong to a booking; each selected person can occupy one requested room. */
final class BookingRooms
{
    /** @return list<string> */
    public static function categories(Trip $trip): array
    {
        return array_values(array_map(static fn($r)=>$r->roomType(),$trip->roomReservations()));
    }

    /** @param list<int|string> $people
     * @return list<array{category:string,personIds:list<int|string>}> */
    public static function validate(Trip $trip,mixed $input,array $people): array
    {
        if(!is_array($input)||!array_is_list($input)||count($input)>100)throw new ValidationException(['rooms'=>'list_max_100']);
        if($input===[]&&self::categories($trip)===[])return [];
        $categories=self::categories($trip);$seen=[];$rooms=[];$owned=[];foreach($people as $person)$owned[(string)$person]=$person;
        foreach($input as $room){
            if(!is_array($room)||array_diff(array_keys($room),['category','personIds'])||!is_string($room['category']??null)||!in_array($room['category'],$categories,true)||!is_array($room['personIds']??null)||!array_is_list($room['personIds'])||$room['personIds']===[])throw new ValidationException(['rooms'=>'category_and_persons_required']);
            $ids=[];
            foreach($room['personIds'] as $pid){
                if((!is_int($pid)&&!is_string($pid))||!array_key_exists((string)$pid,$owned)||in_array((string)$pid,$seen,true))throw new ValidationException(['rooms'=>'assign_each_person_once']);
                $seen[]=(string)$pid;$ids[]=$owned[(string)$pid];
            }
            $rooms[]=['category'=>$room['category'],'personIds'=>$ids];
        }
        if(count($seen)!==count($people))throw new ValidationException(['rooms'=>'assign_each_person_once']);
        return $rooms;
    }
    /** @return list<array{category:string,personIds:list<int|string>}> */
    public static function validateStored(mixed $input): array
    {
        if(!is_array($input)||!array_is_list($input))throw new \LogicException('Invalid stored room requests');
        $rooms=[];
        foreach($input as $room){
            if(!is_array($room)||!is_string($room['category']??null)||!is_array($room['personIds']??null)||!array_is_list($room['personIds']))throw new \LogicException('Invalid stored room request');
            $ids=[];foreach($room['personIds'] as $pid){if(!is_string($pid)&&!is_int($pid))throw new \LogicException('Invalid stored room person');$ids[]=$pid;}
            $rooms[]=['category'=>$room['category'],'personIds'=>$ids];
        }
        return $rooms;
    }
}
