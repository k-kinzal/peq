<?php

declare(strict_types=1);

namespace App\Gql\Datum;

/**
 * What makes two values the same value, for grouping and for removing duplicates.
 *
 * This is deliberately not comparison. `DISTINCT` and `GROUP BY` have to put two
 * nulls in the same group, while `NULL = NULL` is unknown; a query that grouped by a
 * property some rows do not carry would otherwise produce one group per missing
 * value, which is the opposite of what grouping is for.
 *
 * Sameness is decided by writing a value out in a form that is unique to it, and
 * comparing those. That makes a group key a string, which is what a table of groups
 * wants to be keyed by anyway.
 *
 * @visibility App\Gql
 */
final class DatumIdentity
{
    /**
     * Writes a value out in a form that only equal values share.
     *
     * The kind is part of the form, so that a number and the string spelling it do
     * not collapse into one group. Whole and approximate numbers do share a form when
     * they stand for the same number, because grouping by a count should not split
     * into two groups on how the count happened to be computed.
     *
     * @param Datum $value The value to identify
     *
     * @example Two ways of writing the same number identify the same value
     *     \App\Gql\Datum\DatumIdentity::key(new \App\Gql\Datum\IntegerDatum(2)) === \App\Gql\Datum\DatumIdentity::key(new \App\Gql\Datum\FloatDatum(2.0)) // => true
     * @example A number and the string spelling it do not
     *     \App\Gql\Datum\DatumIdentity::key(new \App\Gql\Datum\IntegerDatum(2)) === \App\Gql\Datum\DatumIdentity::key(new \App\Gql\Datum\StringDatum('2')) // => false
     * @example Two absent values identify the same value, which grouping needs
     *     \App\Gql\Datum\DatumIdentity::key(new \App\Gql\Datum\NullDatum()) === \App\Gql\Datum\DatumIdentity::key(new \App\Gql\Datum\NullDatum()) // => true
     *
     * @return string A form unique to values equal to this one
     */
    public static function key(Datum $value): string
    {
        if ($value->kind()->numeric()) {
            return 'number:'.self::number($value);
        }
        if ($value instanceof ListDatum) {
            return 'list:['.implode(',', array_map(self::key(...), $value->items)).']';
        }
        if ($value instanceof PathDatum) {
            return 'path:['.implode(',', array_map(self::key(...), $value->elements)).']';
        }
        if ($value instanceof NodeDatum || $value instanceof EdgeDatum) {
            return 'element:'.$value->kind()->typeName().':'.$value->id;
        }

        return strtolower($value->kind()->typeName()).':'.$value->toText();
    }

    /**
     * Writes a number in a form that does not depend on how it was computed.
     *
     * @param Datum $value The value, which must be of a numeric kind
     *
     * @example A number that came out approximate identifies the whole number it equals
     *     \App\Gql\Datum\DatumIdentity::number(new \App\Gql\Datum\FloatDatum(2.0)) // => '2'
     * @example One with a fractional part keeps it
     *     \App\Gql\Datum\DatumIdentity::number(new \App\Gql\Datum\FloatDatum(2.5)) // => '2.5'
     * @example A result that is not a number groups with the other results that are not
     *     \App\Gql\Datum\DatumIdentity::number(new \App\Gql\Datum\FloatDatum(NAN)) // => 'NAN'
     *
     * @return string The number, in a form equal numbers share
     */
    public static function number(Datum $value): string
    {
        if ($value instanceof DecimalDatum) {
            return self::plain($value->toText());
        }
        $number = DatumOrder::numberOf($value);
        if (is_int($number)) {
            return (string) $number;
        }
        if (is_nan($number)) {
            return 'NAN';
        }
        if (is_infinite($number)) {
            return $number > 0 ? 'INF' : '-INF';
        }

        return self::plain(var_export($number, true));
    }

    /**
     * Writes a number without an exponent and without trailing zeros.
     *
     * A float is keyed by the shortest decimal that reads back as the same float —
     * what PHP writes for it — and an exact number by its digits, so a float and an
     * exact number that `=` finds equal are grouped together, and `0.1 + 0.2` as a
     * float is not grouped with `0.3`, which `=` finds different.
     *
     * @param string $written The number, as `1.5E-7` or `2.50` is written
     *
     * @example An exponent is written out
     *     \App\Gql\Datum\DatumIdentity::plain('1.5E-7') // => '0.00000015'
     * @example Trailing zeros and a bare point are left out
     *     \App\Gql\Datum\DatumIdentity::plain('3.0') // => '3'
     * @example Negative zero is zero
     *     \App\Gql\Datum\DatumIdentity::plain('-0.0') // => '0'
     *
     * @return string The number, written plainly
     */
    public static function plain(string $written): string
    {
        $sign = str_starts_with($written, '-') ? '-' : '';
        $parts = explode('E', strtoupper(ltrim($written, '-+')));
        $mantissa = $parts[0];
        $exponent = $parts[1] ?? '0';
        [$whole, $fraction] = explode('.', $mantissa.'.');
        $digits = $whole.$fraction;
        $point = strlen($whole) + (int) $exponent;
        if ($point <= 0) {
            $digits = str_repeat('0', 1 - $point).$digits;
            $point = 1;
        }
        $digits = str_pad($digits, $point, '0');
        $wholePart = ltrim(substr($digits, 0, $point), '0');
        $fractionPart = rtrim(substr($digits, $point), '0');
        $plain = ($wholePart === '' ? '0' : $wholePart).($fractionPart === '' ? '' : '.'.$fractionPart);

        return $plain === '0' ? '0' : $sign.$plain;
    }

    /**
     * Writes several values out as one form, for grouping by more than one thing.
     *
     * @param list<Datum> $values The values that together decide the group
     *
     * @example Grouping by nothing puts every row in one group
     *     \App\Gql\Datum\DatumIdentity::keyOfAll([]) // => ''
     *
     * @return string A form unique to rows that group together
     */
    public static function keyOfAll(array $values): string
    {
        return implode("\0", array_map(self::key(...), $values));
    }
}
