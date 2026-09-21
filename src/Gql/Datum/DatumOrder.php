<?php

declare(strict_types=1);

namespace App\Gql\Datum;

use App\Gql\GqlException;
use App\Gql\StatusCode;

/**
 * How two values compare, under GQL's three-valued logic.
 *
 * Comparison in GQL is not PHP's comparison and the difference is the whole point of
 * having this class. `NULL = NULL` is not true, it is unknown, and a comparison whose
 * answer is unknown removes the row from a filter rather than keeping it. Two values
 * GQL gives no order between — a number and a string, a node and a list — are not
 * unequal and not undecided either: comparing them is a data exception, "values not
 * comparable". Ordering values of different kinds is optional feature GA04, which peq
 * does not claim.
 *
 * Nodes and edges are compared by identity and only for equality; a path is equal to
 * another when it is made of the same elements in the same order.
 *
 * Sorting is separate from comparing because a column holding nulls still has to be
 * put in some order. Where the nulls go when a query does not say is left to the
 * implementation (IS001), and peq puts them first.
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
     *     \App\Gql\Datum\DatumOrder::equals(new \App\Gql\Datum\IntegerDatum(2), new \App\Gql\Datum\DecimalDatum(20, 1)) // => true
     * @example Nothing equals the absence of a value, not even itself
     *     \App\Gql\Datum\DatumOrder::equals(new \App\Gql\Datum\NullDatum(), new \App\Gql\Datum\NullDatum()) // => null
     * @example Values of unrelated kinds cannot be compared at all
     *     \App\Gql\Datum\DatumOrder::equals(new \App\Gql\Datum\IntegerDatum(5), new \App\Gql\Datum\StringDatum('5')) // throws \App\Gql\GqlException: values not comparable
     *
     * @return null|bool True or false when it can be decided, null when it cannot
     *
     * @throws GqlException If the two values cannot be compared
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

        return self::compare($left, $right) === 0;
    }

    /**
     * Reports whether two lists of values are equal, element by element.
     *
     * @param list<Datum> $left  The values on the left
     * @param list<Datum> $right The values on the right
     *
     * @example Lists of different lengths are unequal
     *     \App\Gql\Datum\DatumOrder::allEqual([new \App\Gql\Datum\IntegerDatum(1)], []) // => false
     *
     * @return null|bool True or false when it can be decided, null when it cannot
     *
     * @throws GqlException If two of the values cannot be compared
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
     * Returns how two values order, or null when either is absent.
     *
     * @param Datum $left  The value on the left
     * @param Datum $right The value on the right
     *
     * @example Numbers order by value, whatever kind of number they are
     *     \App\Gql\Datum\DatumOrder::compare(new \App\Gql\Datum\DecimalDatum(15, 1), new \App\Gql\Datum\IntegerDatum(2)) // => -1
     * @example Strings order by their characters
     *     \App\Gql\Datum\DatumOrder::compare(new \App\Gql\Datum\StringDatum('b'), new \App\Gql\Datum\StringDatum('a')) // => 1
     * @example Nodes have no order
     *     \App\Gql\Datum\DatumOrder::compare(new \App\Gql\Datum\NodeDatum('a'), new \App\Gql\Datum\NodeDatum('b')) // throws \App\Gql\GqlException: values not comparable
     *
     * @return null|int Negative, zero or positive, or null when either value is absent
     *
     * @throws GqlException If the two values have no order between them
     */
    public static function compare(Datum $left, Datum $right): ?int
    {
        if ($left instanceof NullDatum || $right instanceof NullDatum) {
            return null;
        }

        return match (true) {
            $left->kind()->numeric() && $right->kind()->numeric() => self::compareNumbers($left, $right),
            $left instanceof StringDatum && $right instanceof StringDatum => strcmp($left->value, $right->value) <=> 0,
            $left instanceof BooleanDatum && $right instanceof BooleanDatum => $left->value <=> $right->value,
            $left instanceof DateTimeDatum && $right instanceof DateTimeDatum => $left->value <=> $right->value,
            $left instanceof ListDatum && $right instanceof ListDatum => self::compareLists($left->items, $right->items),
            default => throw self::notComparable($left, $right),
        };
    }

    /**
     * Returns how two numbers order, exactly when both are exact.
     *
     * @param Datum $left  The number on the left
     * @param Datum $right The number on the right
     *
     * @example Exact numbers compare exactly, however they are written
     *     \App\Gql\Datum\DatumOrder::compareNumbers(new \App\Gql\Datum\DecimalDatum(10, 1), new \App\Gql\Datum\IntegerDatum(1)) // => 0
     *
     * @return int Negative, zero or positive
     */
    public static function compareNumbers(Datum $left, Datum $right): int
    {
        $exactLeft = $left instanceof IntegerDatum || $left instanceof DecimalDatum;
        $exactRight = $right instanceof IntegerDatum || $right instanceof DecimalDatum;
        if ($exactLeft && $exactRight) {
            return self::compareExact($left, $right);
        }

        return self::numberOf($left) <=> self::numberOf($right);
    }

    /**
     * Returns how two exact numbers order.
     *
     * @param DecimalDatum|IntegerDatum $left  The number on the left
     * @param DecimalDatum|IntegerDatum $right The number on the right
     *
     * @example A whole number and a decimal of the same value are the same
     *     \App\Gql\Datum\DatumOrder::compareExact(new \App\Gql\Datum\DecimalDatum(20, 1), new \App\Gql\Datum\IntegerDatum(2)) // => 0
     *
     * @return int Negative, zero or positive
     */
    public static function compareExact(DecimalDatum|IntegerDatum $left, DecimalDatum|IntegerDatum $right): int
    {
        return self::compareWritten($left->toText(), $right->toText());
    }

    /**
     * Returns how two exact numbers order, from the digits they are written with.
     *
     * Bringing two exact numbers to one scale can take more digits than a PHP integer
     * holds, and a float would round the very difference being asked about. Digits do
     * not overflow: the longer whole part is the larger, and otherwise the digits
     * decide, the shorter fraction read as if it ended in zeros.
     *
     * @param string $left  The number on the left, as `-12.340` or `7` is written
     * @param string $right The number on the right, written the same way
     *
     * @example Two numbers too large to bring to one scale still compare
     *     \App\Gql\Datum\DatumOrder::compareWritten('922337203685477581', '922337203685477580.7') // => 1
     * @example Trailing zeros change nothing
     *     \App\Gql\Datum\DatumOrder::compareWritten('1.50', '1.5') // => 0
     * @example A negative number is smaller the larger its digits
     *     \App\Gql\Datum\DatumOrder::compareWritten('-2', '-1.5') // => -1
     *
     * @return int Negative, zero or positive
     */
    public static function compareWritten(string $left, string $right): int
    {
        $leftNegative = str_starts_with($left, '-') && trim($left, '-0.') !== '';
        $rightNegative = str_starts_with($right, '-') && trim($right, '-0.') !== '';
        if ($leftNegative !== $rightNegative) {
            return $leftNegative ? -1 : 1;
        }
        [$leftWhole, $leftFraction] = explode('.', ltrim($left, '-').'.');
        [$rightWhole, $rightFraction] = explode('.', ltrim($right, '-').'.');
        $leftWhole = ltrim($leftWhole, '0');
        $rightWhole = ltrim($rightWhole, '0');
        $width = max(strlen($leftFraction), strlen($rightFraction));
        $order = [strlen($leftWhole), $leftWhole, str_pad($leftFraction, $width, '0')]
            <=> [strlen($rightWhole), $rightWhole, str_pad($rightFraction, $width, '0')];

        return $leftNegative ? -$order : $order;
    }

    /**
     * Returns how two lists order, element by element and then by length.
     *
     * @param list<Datum> $left  The values on the left
     * @param list<Datum> $right The values on the right
     *
     * @example A list that is a prefix of another orders first
     *     \App\Gql\Datum\DatumOrder::compareLists([new \App\Gql\Datum\IntegerDatum(1)], [new \App\Gql\Datum\IntegerDatum(1), new \App\Gql\Datum\IntegerDatum(2)]) // => -1
     *
     * @return null|int Negative, zero or positive, or null when an element is absent
     *
     * @throws GqlException If two of the elements have no order between them
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
     * Returns how two values order in a sort, where the absence of a value comes first.
     *
     * @param Datum $left  The value on the left
     * @param Datum $right The value on the right
     *
     * @example The absence of a value sorts before any value
     *     \App\Gql\Datum\DatumOrder::sort(new \App\Gql\Datum\NullDatum(), new \App\Gql\Datum\IntegerDatum(1)) // => -1
     * @example Values of kinds with no order between them cannot be sorted together
     *     \App\Gql\Datum\DatumOrder::sort(new \App\Gql\Datum\IntegerDatum(1), new \App\Gql\Datum\StringDatum('a')) // throws \App\Gql\GqlException: values not comparable
     *
     * @return int Negative, zero or positive
     *
     * @throws GqlException If the two values have no order between them
     */
    public static function sort(Datum $left, Datum $right): int
    {
        $absent = ($left instanceof NullDatum ? 0 : 1) <=> ($right instanceof NullDatum ? 0 : 1);
        if ($absent !== 0 || $left instanceof NullDatum) {
            return $absent;
        }

        return self::compare($left, $right) ?? 0;
    }

    /**
     * Returns a number as the PHP number nearest to it.
     *
     * @param Datum $value The number
     *
     * @example A decimal reads as the float nearest to it
     *     \App\Gql\Datum\DatumOrder::numberOf(new \App\Gql\Datum\DecimalDatum(15, 1)) // => 1.5
     *
     * @return float|int The number
     */
    public static function numberOf(Datum $value): float|int
    {
        return match (true) {
            $value instanceof IntegerDatum => $value->value,
            $value instanceof DecimalDatum => $value->toFloat(),
            $value instanceof FloatDatum => $value->value,
            default => 0,
        };
    }

    /**
     * Returns the exception GQL raises for two values with no order between them.
     *
     * @param Datum $left  The value on the left
     * @param Datum $right The value on the right
     *
     * @example The exception names both kinds
     *     \App\Gql\Datum\DatumOrder::notComparable(new \App\Gql\Datum\IntegerDatum(1), new \App\Gql\Datum\StringDatum('a'))->getMessage() // => '[22G04] error: data exception - values not comparable: INT64 and STRING cannot be compared'
     *
     * @return GqlException The exception
     */
    public static function notComparable(Datum $left, Datum $right): GqlException
    {
        return GqlException::because(
            StatusCode::ValuesNotComparable,
            sprintf('%s and %s cannot be compared', $left->kind()->typeName(), $right->kind()->typeName()),
        );
    }
}
