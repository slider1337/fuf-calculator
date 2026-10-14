<?php

declare(strict_types=1);
namespace App\Application;

final class TermFields
{
    public const KEYS = ['title', 'start', 'end', 'location', 'description', 'capacity', 'registrationDeadline', 'visibleToMembers'];

    public static function changes(array $changes): array
    {
        if (!$changes || array_diff(array_keys($changes), self::KEYS)) {
            throw new ValidationException(['changes' => 'known_nonempty_term_fields_required']);
        }
        foreach ($changes as $field => $value) {
            $valid = match ($field) {
                'title' => is_string($value) && trim($value) !== '' && mb_strlen($value) <= 200,
                'location' => is_string($value) && mb_strlen($value) <= 500,
                'description' => is_string($value) && mb_strlen($value) <= 10000,
                'capacity' => is_int($value) && $value > 0 && $value <= 1000000,
                'visibleToMembers' => is_bool($value),
                'registrationDeadline' => $value === null || self::dateTime($value),
                'start', 'end' => self::dateTime($value),
                default => false,
            };
            if (!$valid) { throw new ValidationException(['changes.' . $field => 'invalid_term_value']); }
            if (in_array($field, ['start', 'end', 'registrationDeadline'], true) && $value !== null) {
                $changes[$field] = (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM);
            }
        }
        ksort($changes);
        return $changes;
    }

    public static function period(array $fields): void
    {
        if (!self::dateTime($fields['start'] ?? null) || !self::dateTime($fields['end'] ?? null)
            || new \DateTimeImmutable($fields['end']) < new \DateTimeImmutable($fields['start'])) {
            throw new ValidationException(['changes.end' => 'valid_period_required']);
        }
    }

    public static function dateTime(mixed $value): bool
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D', $value)) { return false; }
        try { $date = new \DateTimeImmutable($value); $errors = \DateTimeImmutable::getLastErrors(); }
        catch (\Throwable) { return false; }
        return ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
    }
}
