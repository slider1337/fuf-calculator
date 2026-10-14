<?php

declare(strict_types=1);
namespace App\Infrastructure;
use App\Application\{AccessException, TermFields};

final class EasyVereinTermFields
{
    public const MAP = ['title'=>'name', 'start'=>'start', 'end'=>'end', 'location'=>'location_name', 'description'=>'prologue', 'capacity'=>'max_participators', 'registrationDeadline'=>'end_participation', 'visibleToMembers'=>'show_memberarea'];
    public static function read(array $row): array
    {
        $fields = [];
        foreach (self::MAP as $field => $provider) {
            if (!array_key_exists($provider, $row)) { throw new AccessException('provider_term_fields_unproven', 503); }
            $value = $row[$provider];
            if (in_array($field, ['start','end','registrationDeadline'], true) && $value !== null) {
                try { $value = (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM); }
                catch (\Throwable) { throw new AccessException('provider_term_fields_unproven', 503); }
            }
            $fields[$field] = $value;
        }
        // Capacity 0 remains an observed value, never an invented unlimited capacity.
        try { TermFields::changes(array_diff_key($fields, ['capacity'=>true])); TermFields::period($fields); }
        catch (\Throwable) { throw new AccessException('provider_term_fields_unproven', 503); }
        if (!is_int($fields['capacity']) || $fields['capacity'] < 0) { throw new AccessException('provider_term_fields_unproven', 503); }
        ksort($fields);
        return $fields;
    }
}
