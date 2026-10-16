<?php

declare(strict_types=1);

namespace App\Application;

/** @phpstan-type FieldValues array<string, string|int|bool|null> */
final class TermFields
{
    public const KEYS = ['title', 'start', 'end', 'location', 'description', 'capacity', 'registrationDeadline', 'visibleToMembers'];

    /** @param array<array-key, mixed> $changes
     * @return FieldValues
     */
    public static function changes(array $changes): array
    {
        if (!$changes || array_diff(array_keys($changes), self::KEYS)) { throw new ValidationException(['changes' => 'known_nonempty_term_fields_required']); }
        return self::validated($changes, false);
    }

    /** Observations may contain capacity zero. They never authorize a change to zero.
     * @param array<array-key, mixed> $fields
     * @return FieldValues
     */
    public static function observed(array $fields): array { return self::validated($fields, true); }

    /** @param array<array-key, mixed> $fields
     * @return FieldValues
     */
    private static function validated(array $fields, bool $observed): array
    {
        $result = [];
        foreach ($fields as $field => $value) {
            $valid = match ($field) {
                'title' => is_string($value) && trim($value) !== '' && mb_strlen($value) <= 200,
                'location' => is_string($value) && mb_strlen($value) <= 500,
                'description' => is_string($value) && mb_strlen($value) <= 10000,
                'capacity' => is_int($value) && ($observed ? $value >= 0 : $value > 0 && $value <= 1000000),
                'visibleToMembers' => is_bool($value),
                'registrationDeadline' => $value === null || self::dateTime($value),
                'start', 'end' => self::dateTime($value),
                default => false,
            };
            if (!$valid || (!is_string($value) && !is_int($value) && !is_bool($value) && $value !== null)) {
                throw new ValidationException(['changes.' . $field => 'invalid_term_value']);
            }
            if (in_array($field, ['start', 'end', 'registrationDeadline'], true) && is_string($value)) {
                $value = (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM);
            }
            $result[$field] = $value;
        }
        ksort($result);
        return $result;
    }

    /** @param array<array-key, mixed> $fields */
    public static function period(array $fields): void
    {
        $start = $fields['start'] ?? null; $end = $fields['end'] ?? null;
        if (!self::dateTime($start) || !self::dateTime($end) || new \DateTimeImmutable($end) < new \DateTimeImmutable($start)) {
            throw new ValidationException(['changes.end' => 'valid_period_required']);
        }
    }

    /** Validate mutations separately: old provider observations must remain readable.
     * @param array<array-key, mixed> $fields
     */
    public static function updatePeriod(array $fields): void
    {
        self::period($fields);
        $deadline = $fields['registrationDeadline'] ?? null;
        $start = $fields['start'] ?? null;
        if (is_string($deadline) && is_string($start) && new \DateTimeImmutable($deadline) >= new \DateTimeImmutable($start)) {
            throw new AccessException('term_registration_overlap', 409);
        }
    }

    /** @phpstan-assert-if-true string $value */
    public static function dateTime(mixed $value): bool
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D', $value)) { return false; }
        try { new \DateTimeImmutable($value); $errors = \DateTimeImmutable::getLastErrors(); }
        catch (\Throwable) { return false; }
        return $errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0);
    }
}
