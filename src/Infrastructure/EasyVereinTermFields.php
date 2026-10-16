<?php

declare(strict_types=1);
namespace App\Infrastructure;
use App\Application\{AccessException, TermFields};

final class EasyVereinTermFields
{
    public const MAP = ['title'=>'name', 'start'=>'start', 'end'=>'end', 'location'=>'location_name', 'description'=>'prologue', 'capacity'=>'max_participators', 'registrationDeadline'=>'end_participation', 'visibleToMembers'=>'show_memberarea'];
    /** @param array<array-key, mixed> $row
     * @return array<string, string|int|bool|null>
     */
    public static function read(array $row): array
    {
        $fields = [];
        foreach (self::MAP as $field => $provider) {
            if (!array_key_exists($provider, $row)) { throw new AccessException('provider_term_fields_unproven', 503); }
            $value = $row[$provider];
            if (in_array($field, ['start','end','registrationDeadline'], true) && $value !== null) {
                try { if (!is_string($value)) { throw new \UnexpectedValueException('Invalid provider timestamp'); } $value = (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM); }
                catch (\Throwable) { throw new AccessException('provider_term_fields_unproven', 503); }
            }
            $fields[$field] = $value;
        }
        // An observed zero is retained; mutations still require positive capacity.
        try { $fields = TermFields::observed($fields); TermFields::period($fields); }
        catch (\Throwable) { throw new AccessException('provider_term_fields_unproven', 503); }
        return $fields;
    }
}
