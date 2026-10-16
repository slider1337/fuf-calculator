<?php

declare(strict_types=1);

namespace App\Application;

/** Validate untrusted values before applying the application's scalar conversions. */
final class Input
{
    public static function scalar(mixed $value): string|int|float|bool|null
    {
        if ($value !== null && !is_scalar($value)) {
            throw new ValidationException(['payload' => 'scalar_required']);
        }
        return $value;
    }

    public static function text(mixed $value): string
    {
        if (!is_string($value)) { throw new ValidationException(['payload' => 'text_required']); }
        return $value;
    }

    /** @return array<array-key,mixed> */
    public static function map(mixed $value): array
    {
        if (!is_array($value)) { throw new ValidationException(['payload' => 'object_required']); }
        return $value;
    }

    /** @return list<array<array-key,mixed>> */
    public static function objects(mixed $value): array
    {
        $items = self::map($value);
        if (!array_is_list($items)) { throw new ValidationException(['payload' => 'list_required']); }
        return array_map(self::map(...), $items);
    }

    /** @return list<string> */
    public static function strings(mixed $value): array
    {
        $items=self::map($value);
        foreach($items as $item){if(!is_string($item))throw new ValidationException(['payload'=>'strings_required']);}
        return array_values(array_filter($items,'is_string'));
    }
    /** @return list<int> */
    public static function integers(mixed $value): array
    {
        $items=self::map($value);
        foreach($items as $item){if(!is_int($item))throw new ValidationException(['payload'=>'integers_required']);}
        return array_values(array_filter($items,'is_int'));
    }
}
