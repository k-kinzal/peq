<?php

declare(strict_types=1);

namespace App\Gql\Evaluation;

use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DatumOrder;
use App\Gql\Datum\ListDatum;
use App\Gql\GqlException;
use App\Gql\StatusCode;

/**
 * Whether a value is among the values of a list.
 *
 * `IN` is the one place three-valued logic behaves in a way that surprises people who
 * know SQL only casually: a value that matches nothing in a list that holds an absent
 * value is undecided, not false, because the absent value might have been the match.
 * Following that rule rather than the convenient one is what keeps `NOT IN` from
 * quietly claiming more than the data supports.
 *
 * @visibility App\Gql
 */
final class Membership
{
    /**
     * Reports whether a value is among the values of a list.
     *
     * @param Datum $value  The value looked for
     * @param Datum $within The list looked in
     *
     * @example A value that is there is there
     *     $within = new \App\Gql\Datum\ListDatum([new \App\Gql\Datum\StringDatum('a'), new \App\Gql\Datum\StringDatum('b')]);
     *     \App\Gql\Evaluation\Membership::of(new \App\Gql\Datum\StringDatum('a'), $within)->toText() // => 'TRUE'
     * @example A value that is not there, in a list of known values, is not
     *     $within = new \App\Gql\Datum\ListDatum([new \App\Gql\Datum\StringDatum('a')]);
     *     \App\Gql\Evaluation\Membership::of(new \App\Gql\Datum\StringDatum('c'), $within)->toText() // => 'FALSE'
     * @example A list holding an absent value cannot rule anything out
     *     $within = new \App\Gql\Datum\ListDatum([new \App\Gql\Datum\StringDatum('a'), new \App\Gql\Datum\NullDatum()]);
     *     \App\Gql\Evaluation\Membership::of(new \App\Gql\Datum\StringDatum('c'), $within)->kind() // => \App\Gql\Datum\DatumKind::Null
     *
     * @return Datum True, false, or the absence of an answer
     *
     * @throws GqlException If what is looked in is not a list
     */
    public static function of(Datum $value, Datum $within): Datum
    {
        if ($within->kind() === DatumKind::Null || $value->kind() === DatumKind::Null) {
            return Logic::datum(null);
        }
        if (!$within instanceof ListDatum) {
            throw GqlException::because(
                StatusCode::InvalidType,
                sprintf('a list was expected, and a %s was given', $within->kind()->typeName()),
            );
        }

        $undecided = false;
        foreach ($within->items as $item) {
            $equal = DatumOrder::equals($value, $item);
            if ($equal === true) {
                return Logic::datum(true);
            }
            $undecided = $undecided || $equal === null;
        }

        return Logic::datum($undecided ? null : false);
    }
}
