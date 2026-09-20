<?php

declare(strict_types=1);

namespace App\Gql\Evaluation;

use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\StringDatum;

/**
 * Writing one value after another, which is the only operator GQL spells with strings.
 *
 * The three predicates a reader coming from another graph language reaches for first —
 * `CONTAINS`, `STARTS WITH`, `ENDS WITH` — are not here, because ISO/IEC 39075 does not
 * define them. Microsoft's reserved word reference marks all three as reserved by graph
 * in Fabric for its own extensions, which is the vendor saying so. A prefix is asked
 * for with `left`, a suffix with `right`, and both are in the standard's `<substring
 * function>`.
 *
 * Joining is lenient about what it joins. GQL's `||` is defined over strings, but a
 * query that writes `'line ' || p.line` means exactly what it looks like, and refusing
 * it would only make the reader wrap it in a conversion. Two lists joined produce a
 * list, which is the other thing `||` means in the standard.
 *
 * @visibility App\Gql
 */
final class TextOperation
{
    /**
     * Joins two values, as strings or as lists.
     *
     * Joining anything to the absence of a value has no answer, which is the rule GQL
     * takes from SQL. A query that wants the absence treated as an empty string says
     * so with `coalesce`.
     *
     * @param Datum $left  The value on the left
     * @param Datum $right The value on the right
     *
     * @example Two strings join into a longer one
     *     \App\Gql\Evaluation\TextOperation::concatenate(new \App\Gql\Datum\StringDatum('a'), new \App\Gql\Datum\StringDatum('b'))->toText() // => 'ab'
     * @example Two lists join into a longer one
     *     $left = new \App\Gql\Datum\ListDatum([new \App\Gql\Datum\IntegerDatum(1)]);
     *     $right = new \App\Gql\Datum\ListDatum([new \App\Gql\Datum\IntegerDatum(2)]);
     *     \App\Gql\Evaluation\TextOperation::concatenate($left, $right)->toText() // => '[1, 2]'
     * @example Joining to something that is not there has no answer
     *     $absent = new \App\Gql\Datum\NullDatum();
     *     \App\Gql\Evaluation\TextOperation::concatenate(new \App\Gql\Datum\StringDatum('a'), $absent)->kind() // => \App\Gql\Datum\DatumKind::Null
     *
     * @return Datum The two joined
     */
    public static function concatenate(Datum $left, Datum $right): Datum
    {
        if ($left->kind() === DatumKind::Null || $right->kind() === DatumKind::Null) {
            return new NullDatum();
        }
        if ($left instanceof ListDatum && $right instanceof ListDatum) {
            return new ListDatum([...$left->items, ...$right->items]);
        }

        return new StringDatum($left->toText().$right->toText());
    }
}
