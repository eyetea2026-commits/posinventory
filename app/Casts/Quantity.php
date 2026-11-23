<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class Quantity implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): int|float|null
    {
        return self::normalize($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): int|float|null
    {
        return self::normalize($value);
    }

    public static function normalize(mixed $value): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        $rounded = round((float) $value, 2);

        return floor($rounded) == $rounded ? (int) $rounded : $rounded;
    }
}
