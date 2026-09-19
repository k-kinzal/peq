<?php

declare(strict_types=1);

namespace App\Gql\Argument;

use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\FloatDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\GqlException;
use App\Gql\StatusCode;

/**
 * Reading a value as the number an operation was given.
 *
 * Arithmetic wants it, and so do the summaries that add and average — and neither
 * belongs to the other. Reading it here rather than in either of them is what keeps
 * the operators and the standard library from having to name each other, and it keeps
 * one wording for the mistake a query makes by adding up a column of names.
 *
 * @visibility App\Gql
 */
final class NumberArgument
{
    /**
     * Reads a value as a number.
     *
     * @param Datum $value The value
     *
     * @example A whole number reads as an integer, and keeps being one
     *     \App\Gql\Argument\NumberArgument::of(new \App\Gql\Datum\IntegerDatum(2)) // => 2
     * @example An approximate one reads as a float
     *     \App\Gql\Argument\NumberArgument::of(new \App\Gql\Datum\FloatDatum(1.5)) // => 1.5
     * @example Anything else is reported under the status GQL gives it
     *     \App\Gql\Argument\NumberArgument::of(new \App\Gql\Datum\StringDatum('2')) // throws \App\Gql\GqlException: invalid value type
     *
     * @return float|int The number
     *
     * @throws GqlException If the value is not a number
     */
    public static function of(Datum $value): float|int
    {
        if ($value instanceof IntegerDatum) {
            return $value->value;
        }
        if ($value instanceof FloatDatum) {
            return $value->value;
        }

        throw GqlException::because(
            StatusCode::InvalidType,
            sprintf('a number was expected, and a %s was given', $value->kind()->typeName()),
        );
    }

    /**
     * Reports whether a value is an approximate number.
     *
     * An expression that meets one produces an approximate result, so every operation
     * over numbers has to ask this of every value it is given.
     *
     * @param Datum $value The value
     *
     * @example An approximate number makes what it touches approximate
     *     \App\Gql\Argument\NumberArgument::approximate(new \App\Gql\Datum\FloatDatum(1.5)) // => true
     * @example A whole one does not
     *     \App\Gql\Argument\NumberArgument::approximate(new \App\Gql\Datum\IntegerDatum(2)) // => false
     *
     * @return bool True when it is approximate
     */
    public static function approximate(Datum $value): bool
    {
        return $value->kind() === DatumKind::Float;
    }
}
