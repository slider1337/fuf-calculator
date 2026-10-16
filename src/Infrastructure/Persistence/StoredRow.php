<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use UnexpectedValueException;

/** Validate database values at the boundary instead of casting unknown input. */
final readonly class StoredRow
{
    /** @param array<array-key, mixed> $values */
    private function __construct(private array $values) {}

    public static function from(mixed $value): self
    {
        if (!is_array($value)) { throw new UnexpectedValueException('Expected a database row'); }
        return new self($value);
    }

    /** @return array<array-key,mixed> */
    public function values(): array { return $this->values; }

    public function text(string $key): string
    {
        $value = $this->values[$key] ?? null;
        if (!is_string($value)) { throw new UnexpectedValueException('Invalid text column: ' . $key); }
        return $value;
    }

    public function nullableText(string $key): ?string
    {
        return ($this->values[$key] ?? null) === null ? null : $this->text($key);
    }

    public function integer(string $key): int
    {
        $value = $this->values[$key] ?? null;
        if (is_int($value)) { return $value; }
        if (is_string($value) && preg_match('/^-?\d+$/D', $value)) {
            $integer = filter_var($value, FILTER_VALIDATE_INT);
            if ($integer !== false) { return $integer; }
        }
        throw new UnexpectedValueException('Invalid integer column: ' . $key);
    }

    public function nullableInteger(string $key): ?int
    {
        return ($this->values[$key] ?? null) === null ? null : $this->integer($key);
    }

    /** @return array<array-key, mixed> */
    public function jsonArray(string $key): array
    {
        $value = json_decode($this->text($key), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($value)) { throw new UnexpectedValueException('Invalid JSON array column: ' . $key); }
        return $value;
    }

    /** @return array<string,scalar|null> */
    public static function scalars(mixed $value): array
    {
        if (!is_array($value)) { throw new UnexpectedValueException('Expected a database row'); }
        $result = [];
        foreach ($value as $key => $item) {
            if (!is_string($key) || (!is_scalar($item) && $item !== null)) {
                throw new UnexpectedValueException('Invalid database column');
            }
            $result[$key] = $item;
        }
        return $result;
    }

    /** @return array<string,scalar|null>|false */
    public static function fetch(\PDOStatement|false $statement): array|false
    {
        if ($statement === false) { throw new UnexpectedValueException('Database query failed'); }
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        return $row === false ? false : self::scalars($row);
    }

    /** @return list<array<string,scalar|null>> */
    public static function fetchAll(\PDOStatement|false $statement): array
    {
        if ($statement === false) { throw new UnexpectedValueException('Database query failed'); }
        return array_values(array_map(self::scalars(...), $statement->fetchAll(\PDO::FETCH_ASSOC)));
    }

    public function number(string $key): float
    {
        $value = $this->values[$key] ?? null;
        if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
            throw new UnexpectedValueException('Invalid numeric column: ' . $key);
        }
        $number = (float) $value;
        if (!is_finite($number)) { throw new UnexpectedValueException('Non-finite column: ' . $key); }
        return $number;
    }
}
