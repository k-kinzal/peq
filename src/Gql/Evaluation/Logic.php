<?php

declare(strict_types=1);

namespace App\Gql\Evaluation;

use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\Datum;
use App\Gql\Datum\NullDatum;
use App\Gql\GqlException;
use App\Gql\StatusCode;

/**
 * GQL's three-valued logic, written out.
 *
 * The third value is the one that catches people out. A comparison against something
 * absent is not false, it is undecided, and undecided survives negation: `NOT
 * UNKNOWN` is still unknown. That is why `FILTER NOT (p.line > 0)` does not keep the
 * symbols with no line, and why a query that wants them has to say `IS NULL`.
 *
 * Writing the tables out rather than leaning on PHP's own operators is the point of
 * this class. PHP has two truth values and coerces everything else into them; GQL has
 * three and coerces nothing.
 *
 * @visibility App\Gql
 */
final class Logic
{
    /**
     * Reads a value as a truth value, or reports that it is not one.
     *
     * The absence of a value reads as undecided, which is the whole arrangement: GQL
     * uses one representation for "no value" and "no answer", so a predicate over
     * missing data comes out undecided without anything having to say so.
     *
     * @param Datum $value The value to read
     *
     * @example Truth reads as truth
     *     \App\Gql\Evaluation\Logic::truth(new \App\Gql\Datum\BooleanDatum(true)) // => true
     * @example The absence of a value reads as undecided
     *     \App\Gql\Evaluation\Logic::truth(new \App\Gql\Datum\NullDatum()) // => null
     * @example Anything else is a mistake rather than a truth value
     *     \App\Gql\Evaluation\Logic::truth(new \App\Gql\Datum\IntegerDatum(1)) // throws \App\Gql\GqlException: invalid value type
     *
     * @return null|bool True, false, or null for undecided
     *
     * @throws GqlException If the value is not a truth value at all
     */
    public static function truth(Datum $value): ?bool
    {
        if ($value instanceof BooleanDatum) {
            return $value->value;
        }
        if ($value instanceof NullDatum) {
            return null;
        }

        throw GqlException::because(
            StatusCode::InvalidType,
            sprintf('a truth value was expected, and a %s was given', $value->kind()->typeName()),
        );
    }

    /**
     * Returns a truth value as the value a query carries it as.
     *
     * @param null|bool $truth The truth value, or null for undecided
     *
     * @example Undecided is carried as the absence of a value
     *     \App\Gql\Evaluation\Logic::datum(null)->kind() // => \App\Gql\Datum\DatumKind::Null
     * @example A decided one is carried as itself
     *     \App\Gql\Evaluation\Logic::datum(true)->toText() // => 'TRUE'
     *
     * @return Datum The value
     */
    public static function datum(?bool $truth): Datum
    {
        return $truth === null ? new NullDatum() : new BooleanDatum($truth);
    }

    /**
     * Returns whether both hold, under three-valued logic.
     *
     * One side being false settles it whatever the other is, which is what makes a
     * conjunction usable as a guard: `p.line IS NOT NULL AND p.line > 0` is false
     * rather than undecided for a symbol with no line.
     *
     * @param null|bool $left  The truth value on the left
     * @param null|bool $right The truth value on the right
     *
     * @example One side being false settles it
     *     \App\Gql\Evaluation\Logic::both(false, null) // => false
     * @example Otherwise, being undecided spreads
     *     \App\Gql\Evaluation\Logic::both(true, null) // => null
     *
     * @return null|bool True, false, or null for undecided
     */
    public static function both(?bool $left, ?bool $right): ?bool
    {
        if ($left === false || $right === false) {
            return false;
        }

        return $left === null || $right === null ? null : true;
    }

    /**
     * Returns whether either holds, under three-valued logic.
     *
     * @param null|bool $left  The truth value on the left
     * @param null|bool $right The truth value on the right
     *
     * @example One side being true settles it
     *     \App\Gql\Evaluation\Logic::either(true, null) // => true
     * @example Otherwise, being undecided spreads
     *     \App\Gql\Evaluation\Logic::either(false, null) // => null
     *
     * @return null|bool True, false, or null for undecided
     */
    public static function either(?bool $left, ?bool $right): ?bool
    {
        if ($left === true || $right === true) {
            return true;
        }

        return $left === null || $right === null ? null : false;
    }

    /**
     * Returns whether exactly one holds, under three-valued logic.
     *
     * Nothing settles this while either side is undecided, because the answer depends
     * on both of them.
     *
     * @param null|bool $left  The truth value on the left
     * @param null|bool $right The truth value on the right
     *
     * @example Exactly one of two decided values
     *     \App\Gql\Evaluation\Logic::exclusive(true, false) // => true
     * @example Nothing can be said while either is undecided
     *     \App\Gql\Evaluation\Logic::exclusive(true, null) // => null
     *
     * @return null|bool True, false, or null for undecided
     */
    public static function exclusive(?bool $left, ?bool $right): ?bool
    {
        if ($left === null || $right === null) {
            return null;
        }

        return $left !== $right;
    }

    /**
     * Returns the opposite, under three-valued logic.
     *
     * @param null|bool $truth The truth value, or null for undecided
     *
     * @example The opposite of a decided value is decided
     *     \App\Gql\Evaluation\Logic::negate(true) // => false
     * @example The opposite of undecided is undecided
     *     \App\Gql\Evaluation\Logic::negate(null) // => null
     *
     * @return null|bool True, false, or null for undecided
     */
    public static function negate(?bool $truth): ?bool
    {
        return $truth === null ? null : !$truth;
    }

    /**
     * Reports whether a value is the one thing a filter keeps.
     *
     * A filter keeps the rows a predicate is true of, and only those: false and
     * undecided are both dropped. Asking it as one question rather than as two is
     * what keeps that rule in one place.
     *
     * @param Datum $value The value the predicate came out as
     *
     * @example A predicate that came out true keeps its row
     *     \App\Gql\Evaluation\Logic::holds(new \App\Gql\Datum\BooleanDatum(true)) // => true
     * @example One that could not be decided does not
     *     \App\Gql\Evaluation\Logic::holds(new \App\Gql\Datum\NullDatum()) // => false
     *
     * @return bool True when the predicate is true
     *
     * @throws GqlException If the value is not a truth value at all
     */
    public static function holds(Datum $value): bool
    {
        return self::truth($value) === true;
    }
}
