<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Units of measurement a product can be stocked, bought and sold in, and
 * how quantities in them are written ("1 meter", "4,999 meters",
 * "1.5 meters", "2 Boxes").
 */
class UnitOfMeasure
{
    const DEFAULT = 'piece';

    const UNITS = ['piece', 'meter', 'foot', 'roll', 'reel', 'box', 'pack', 'set', 'pair'];

    /**
     * 4,999 / 1.5 / 1.25 - never "1.50" or "10.00".
     */
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

    /**
     * The unit's name for that many: "meter" for 1, "meters" otherwise.
     */
    public static function label(string $unit, int|float|null $quantity): string
    {
        return abs((float) $quantity) == 1 ? $unit : self::plural($unit);
    }

    /**
     * For tables: a plain count for pieces (as always), "1.5 meters" in
     * any other unit; $signed adds "+" to an increase.
     */
    public static function cell(int|float|null $quantity, ?string $unit, bool $signed = false): string
    {
        $text = ! $unit || $unit === self::DEFAULT ? self::number($quantity) : self::format($quantity, $unit);

        return $signed && (float) $quantity >= 0 ? '+'.$text : $text;
    }

    /**
     * Quantities in different units added up per unit: "12 pieces,
     * 5,000 meters" - or null when they're all pieces (a plain total).
     *
     * @param  iterable<array{0: int|float|null, 1: ?string}>  $quantitiesWithUnits
     */
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

    /**
     * "1 meter", "4,999 meters"; with $capitalized, "2 Boxes".
     */
    public static function format(int|float|null $quantity, string $unit, bool $capitalized = false): string
    {
        $label = self::label($unit, $quantity);

        return self::number($quantity).' '.($capitalized ? ucfirst($label) : $label);
    }
}
