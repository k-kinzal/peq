<?php

declare(strict_types=1);

namespace App\Gql\Datum;

/**
 * How two values compare, under GQL's three-valued logic.
 *
 * Comparison in GQL is not PHP's comparison and the difference is the whole point of
 * having this class. `NULL = NULL` is not true, it is unknown; a comparison whose
 * answer is unknown removes the row from a filter rather than keeping it; and a
 * comparison between values of unrelated kinds has an answer — false for equality,
 * unknown for ordering — rather than an accident.
 *
 * Sorting is separate from comparing for the same reason. A query that sorts a column
 * holding nulls still has to produce an order, so sorting is total and puts null
 * first, while comparing stays honest and says it does not know.
 *
 * @visibility App\Gql
 */
final class DatumOrder
{
    /**
     * Reports whether two values are equal, or that it cannot be decided.
     *
     * @param Datum $left  The value on the left of the comparison
     * @param Datum $right The value on the right of it
     *
     * @example Two numbers of different kinds compare as numbers
     *     \App\Gql\Datum\DatumOrder::equals(new \App\Gql\Datum\IntegerDatum(2), new \App\Gql\Datum\FloatDatum(2.0)) // => true
     * @example Nothing equals the absence of a value, not even itself
     *     \App\Gql\Datum\DatumOrder::equals(new \App\Gql\Datum\NullDatum(), new \App\Gql\Datum\NullDatum()) // => null
     * @example Values of unrelated kinds are unequal rather than undecidable
     *     \App\Gql\Datum\DatumOrder::equals(new \App\Gql\Datum\IntegerDatum(5), new \App\Gql\Datum\StringDatum('5')) // => false
     *
     * @return null|bool True or false when it can be decided, null when it cannot
     */
    public static function equals(Datum $left, Datum $right): ?bool
    {
        if ($left instanceof NullDatum || $right instanceof NullDatum) {
            return null;
        }
        if ($left instanceof NodeDatum && $right instanceof NodeDatum) {
            return $left->id === $right->id;
        }
        if ($left instanceof EdgeDatum && $right instanceof EdgeDatum) {
            return $left->id === $right->id;
        }
        if ($left instanceof PathDatum && $right instanceof PathDatum) {
            return self::allEqual($left->elements, $right->elements);
        }
        if ($left instanceof ListDatum && $right instanceof ListDatum) {
            return self::allEqual($left->items, $right->items);
        }

        $order = self::compare($left, $right);

        return $order !== null && $order === 0;
    }

    /**
     * Reports whether two lists hold equal values, or that it cannot be decided.
     *
     * Lists of different lengths are unequal whatever they hold, because no pair of
     * their values could make up the difference. Otherwise one undecidable pair makes
     * the whole comparison undecidable, but only if no pair is outright unequal:
     * a difference found is a difference, whatever else could not be decided.
     *
     * @param list<Datum> $left  The values on the left of the comparison
     * @param list<Datum> $right The values on the right of it
     *
     * @example Lists of different lengths differ whatever they hold
     *     \App\Gql\Datum\DatumOrder::allEqual([new \App\Gql\Datum\IntegerDatum(1)], []) // => false
     * @example A difference found outweighs a pair that could not be decided
     *     $left = [new \App\Gql\Datum\NullDatum(), new \App\Gql\Datum\IntegerDatum(1)];
     *     $right = [new \App\Gql\Datum\NullDatum(), new \App\Gql\Datum\IntegerDatum(2)];
     *     \App\Gql\Datum\DatumOrder::allEqual($left, $right) // => false
     *
     * @return null|bool True or false when it can be decided, null when it cannot
     */
    public static function allEqual(array $left, array $right): ?bool
    {
        if (count($left) !== count($right)) {
            return false;
        }

        $undecided = false;
        foreach ($left as $index => $value) {
            $equal = self::equals($value, $right[$index]);
            if ($equal === false) {
                return false;
            }
            $undecided = $undecided || $equal === null;
        }

        return $undecided ? null : true;
    }

    /**
     * Reports how two values order against each other, or that it cannot be decided.
     *
     * @param Datum $left  The value on the left of the comparison
     * @param Datum $right The value on the right of it
     *
     * @example Numbers order as numbers, whichever kind they are
     *     \App\Gql\Datum\DatumOrder::compare(new \App\Gql\Datum\IntegerDatum(2), new \App\Gql\Datum\FloatDatum(2.5)) // => -1
     * @example Nothing orders against the absence of a value
     *     \App\Gql\Datum\DatumOrder::compare(new \App\Gql\Datum\IntegerDatum(2), new \App\Gql\Datum\NullDatum()) // => null
     * @example Nor do values with no order between their kinds
     *     \App\Gql\Datum\DatumOrder::compare(new \App\Gql\Datum\IntegerDatum(2), new \App\Gql\Datum\StringDatum('2')) // => null
     *
     * @return null|int Negative, zero or positive when it can be decided, null when it cannot
     */
    public static function compare(Datum $left, Datum $right): ?int
    {
        if ($left instanceof NullDatum || $right instanceof NullDatum) {
            return null;
        }
        if ($left->kind()->numeric() && $right->kind()->numeric()) {
            return self::numberOf($left) <=> self::numberOf($right);
        }
        if ($left instanceof StringDatum && $right instanceof StringDatum) {
            return strcmp($left->value, $right->value) <=> 0;
        }
        if ($left instanceof BooleanDatum && $right instanceof BooleanDatum) {
            return $left->value <=> $right->value;
        }
        if ($left instanceof DateTimeDatum && $right instanceof DateTimeDatum) {
            return $left->value <=> $right->value;
        }
        if ($left instanceof ListDatum && $right instanceof ListDatum) {
            return self::compareLists($left->items, $right->items);
        }

        return null;
    }

    /**
     * Reports how two lists order against each other, or that it cannot be decided.
     *
     * Lists order the way words do: by their first differing value, and by length
     * when one runs out first.
     *
     * @param list<Datum> $left  The values on the left of the comparison
     * @param list<Datum> $right The values on the right of it
     *
     * @example Lists order by their first difference
     *     $left = [new \App\Gql\Datum\IntegerDatum(1), new \App\Gql\Datum\IntegerDatum(2)];
     *     $right = [new \App\Gql\Datum\IntegerDatum(1), new \App\Gql\Datum\IntegerDatum(3)];
     *     \App\Gql\Datum\DatumOrder::compareLists($left, $right) // => -1
     * @example A list that runs out first is the smaller one
     *     \App\Gql\Datum\DatumOrder::compareLists([], [new \App\Gql\Datum\IntegerDatum(1)]) // => -1
     *
     * @return null|int Negative, zero or positive when it can be decided, null when it cannot
     */
    public static function compareLists(array $left, array $right): ?int
    {
        $shared = min(count($left), count($right));
        for ($index = 0; $index < $shared; ++$index) {
            $order = self::compare($left[$index], $right[$index]);
            if ($order === null) {
                return null;
            }
            if ($order !== 0) {
                return $order;
            }
        }

        return count($left) <=> count($right);
    }

    /**
     * Orders two values for sorting, deciding every pair.
     *
     * Sorting has to terminate on whatever a column happens to hold, so this answers
     * where comparing refuses to. Null is the smallest value there is, as GQL says;
     * values of different kinds are ordered by their kinds; values with no order of
     * their own — nodes, edges, paths — are ordered by how they are written, so that
     * the same query run twice sorts them the same way.
     *
     * @param Datum $left  The value on the left of the sort
     * @param Datum $right The value on the right of it
     *
     * @example The absence of a value sorts before every value
     *     \App\Gql\Datum\DatumOrder::sort(new \App\Gql\Datum\NullDatum(), new \App\Gql\Datum\IntegerDatum(-99)) // => -1
     * @example Values with no order of their own are sorted by how they are written
     *     $left = new \App\Gql\Datum\NodeDatum('App\\A');
     *     $right = new \App\Gql\Datum\NodeDatum('App\\B');
     *     \App\Gql\Datum\DatumOrder::sort($left, $right) // => -1
     *
     * @return int Negative, zero or positive
     */
    public static function sort(Datum $left, Datum $right): int
    {
        $byKind = $left->kind()->rank() <=> $right->kind()->rank();
        if ($byKind !== 0) {
            return $byKind;
        }

        $order = self::compare($left, $right);
        if ($order !== null) {
            return $order;
        }
        if ($left instanceof NullDatum && $right instanceof NullDatum) {
            return 0;
        }

        return strcmp($left->toText(), $right->toText()) <=> 0;
    }

    /**
     * Reads a numeric value as the PHP number it stands for.
     *
     * @param Datum $value The value, which must be of a numeric kind
     *
     * @example A whole number reads as an integer
     *     \App\Gql\Datum\DatumOrder::numberOf(new \App\Gql\Datum\IntegerDatum(2)) // => 2
     * @example Anything that is not a number reads as zero, which no comparison reaches
     *     \App\Gql\Datum\DatumOrder::numberOf(new \App\Gql\Datum\StringDatum('2')) // => 0
     *
     * @return float|int The number it stands for
     */
    public static function numberOf(Datum $value): float|int
    {
        if ($value instanceof IntegerDatum) {
            return $value->value;
        }

        return $value instanceof FloatDatum ? $value->value : 0;
    }
}
