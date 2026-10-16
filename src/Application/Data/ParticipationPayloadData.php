<?php

declare(strict_types=1);

namespace App\Application\Data;

/** @phpstan-import-type ParticipationPayload from \App\Application\Port\ParticipationPort */
final class ParticipationPayloadData
{
    /** @return ParticipationPayload */
    public static function from(mixed $value): array
    {
        if (!is_array($value) || !is_string($value['eventId'] ?? null) || !is_string($value['memberReference'] ?? null)) {
            throw new \UnexpectedValueException('Invalid stored participation identity');
        }
        $result = ['eventId'=>$value['eventId'],'memberReference'=>$value['memberReference']];
        foreach (['externalId','priceGroupId','price'] as $key) {
            if (!array_key_exists($key,$value) || ($value[$key] !== null && !is_string($value[$key]))) {
                throw new \UnexpectedValueException('Invalid stored participation price or reference');
            }
        }
        $external = $value['externalId']; $group = $value['priceGroupId']; $price = $value['price'];
        $result += ['externalId'=>$external,'priceGroupId'=>$group,'price'=>$price];
        if (isset($value['guestId'])) {
            if (!is_int($value['guestId']) || !is_string($value['guestName'] ?? null)) { throw new \UnexpectedValueException('Invalid stored guest'); }
            $result += ['guestId'=>$value['guestId'],'guestName'=>$value['guestName']];
        }
        return $result;
    }
}
