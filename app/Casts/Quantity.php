<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A stock quantity in a decimal(12,2) column: 1.5 meters can be sold,
 * but a whole number reads back as an int (10, not 10.0 or "10.00"), so
 * piece-counted products keep behaving exactly as they did.
 */
class Quantity implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): int|float|null
    {
        return self::normalize($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): int|float|null
    {
        return self::normalize($value);
    }

    /**
     * Rounded to the column's 2 decimals; whole numbers as int.
     */
    public static function normalize(mixed $value): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        $rounded = round((float) $value, 2);

        return floor($rounded) == $rounded ? (int) $rounded : $rounded;
    }
}
