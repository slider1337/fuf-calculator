<?php

declare(strict_types=1);

namespace App\Application\Data;

use App\Application\ValidationException;

final class BookingPersonData
{
    /** @return list<array{id:int|string,name:string,birthDate:string,...}> */
    public static function list(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) { throw new ValidationException(['persons'=>'list_required']); }
        $result=[];
        foreach ($value as $person) {
            if (!is_array($person) || (!is_int($person['id'] ?? null) && !is_string($person['id'] ?? null))
                || !is_string($person['name'] ?? null) || !is_string($person['birthDate'] ?? null)) {
                throw new ValidationException(['persons'=>'stable_id_name_and_birth_date_required']);
            }
            $result[]=['id'=>$person['id'],'name'=>$person['name'],'birthDate'=>$person['birthDate']]+$person;
        }
        return $result;
    }
}
