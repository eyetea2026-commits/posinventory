<?php

namespace App\Services;

use Illuminate\Support\Str;

class UnitOfMeasure
{
    const DEFAULT = 'piece';

    const UNITS = ['piece', 'meter', 'roll', 'box'];

    const WIRE_CATEGORY = 'WIRE';

    const WIRE_UNITS = ['meter', 'roll'];

    const OTHER_UNITS = ['piece', 'box'];

    const METER_AND_ROLL = 'meter_roll';

    const METER_AND_ROLL_LABEL = 'Meter & Roll';

    public static function forCategory(?string $categoryName): array
    {
        return self::isWire($categoryName) ? self::WIRE_UNITS : self::OTHER_UNITS;
    }

    public static function baseChoicesFor(?string $categoryName): array
    {
        return self::isWire($categoryName) ? [self::METER_AND_ROLL, ...self::WIRE_UNITS] : self::OTHER_UNITS;
    }

    private static function isWire(?string $categoryName): bool
    {
        return strcasecmp(trim((string) $categoryName), self::WIRE_CATEGORY) === 0;
    }

    public static function number(int|float|null $quantity): string
    {
        $quantity = round((float) $quantity, 2);

        return floor($quantity) == $quantity
            ? number_format($quantity)
            : rtrim(rtrim(number_format($quantity, 2), '0'), '.');
    }

    public static function plural(string $unit): string
    {
        return Str::plural($unit);
    }

    public static function label(string $unit, int|float|null $quantity): string
    {
        return abs((float) $quantity) == 1 ? $unit : self::plural($unit);
    }

    public static function cell(int|float|null $quantity, ?string $unit, bool $signed = false): string
    {
        $text = ! $unit || $unit === self::DEFAULT ? self::number($quantity) : self::format($quantity, $unit);

        return $signed && (float) $quantity >= 0 ? '+'.$text : $text;
    }

    public static function totals(iterable $quantitiesWithUnits): ?string
    {
        $totals = [];
        foreach ($quantitiesWithUnits as [$quantity, $unit]) {
            $unit = $unit ?: self::DEFAULT;
            $totals[$unit] = ($totals[$unit] ?? 0) + (float) $quantity;
        }

        if (array_keys($totals) === [] || array_keys($totals) === [self::DEFAULT]) {
            return null;
        }

        return implode(', ', array_map(fn ($unit) => self::format($totals[$unit], $unit), array_keys($totals)));
    }

    public static function format(int|float|null $quantity, string $unit, bool $capitalized = false): string
    {
        $label = self::label($unit, $quantity);

        return self::number($quantity).' '.($capitalized ? ucfirst($label) : $label);
    }
}
