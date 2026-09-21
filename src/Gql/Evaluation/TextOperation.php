<?php

declare(strict_types=1);

namespace App\Gql\Evaluation;

use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\GqlException;
use App\Gql\StatusCode;

/**
 * Writing one value after another, which is the only operator GQL spells with strings.
 *
 * `||` joins two character strings into one and two lists into one, and that is all it
 * does: ISO/IEC 39075 writes `<character string concatenation>` and `<list
 * concatenation>` and nothing that joins a string to a number. `'line ' || 12` is
 * therefore a value of the wrong type rather than `'line 12'`.
 *
 * The three predicates a reader coming from another graph language reaches for first —
 * `CONTAINS`, `STARTS WITH`, `ENDS WITH` — are not here either, because the standard
 * does not define them. A prefix is asked for with `left`, a suffix with `right`.
 *
 * @visibility App\Gql
 */
final class TextOperation
{
    /**
     * Joins two strings, or two lists.
     *
     * Joining anything to the absence of a value has no answer, which is the rule GQL
     * takes from SQL.
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
     * @example A string and a number do not join
     *     \App\Gql\Evaluation\TextOperation::concatenate(new \App\Gql\Datum\StringDatum('a'), new \App\Gql\Datum\IntegerDatum(1)) // throws \App\Gql\GqlException: invalid value type
     * @example Joining to something that is not there has no answer
     *     $absent = new \App\Gql\Datum\NullDatum();
     *     \App\Gql\Evaluation\TextOperation::concatenate(new \App\Gql\Datum\StringDatum('a'), $absent)->kind() // => \App\Gql\Datum\DatumKind::Null
     *
     * @return Datum The two joined
     *
     * @throws GqlException If the two are not both strings or both lists
     */
    public static function concatenate(Datum $left, Datum $right): Datum
    {
        if ($left->kind() === DatumKind::Null || $right->kind() === DatumKind::Null) {
            return new NullDatum();
        }
        if ($left instanceof StringDatum && $right instanceof StringDatum) {
            return new StringDatum($left->value.$right->value);
        }
        if ($left instanceof ListDatum && $right instanceof ListDatum) {
            return new ListDatum([...$left->items, ...$right->items]);
        }

        throw GqlException::because(
            StatusCode::InvalidType,
            sprintf('|| joins two strings or two lists, and was given %s and %s', $left->kind()->typeName(), $right->kind()->typeName()),
        );
    }
}
