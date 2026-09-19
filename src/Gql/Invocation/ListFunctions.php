<?php

declare(strict_types=1);

namespace App\Gql\Invocation;

use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\PathDatum;
use App\Gql\GqlException;
use App\Gql\StatusCode;

/**
 * GQL's functions over lists.
 *
 * On a graph of source code, the list that matters is the one a variable-length
 * pattern binds: every edge along a match. `size(e)` is therefore not a small utility
 * but the answer to "how far away is this" — the number of calls between a controller
 * and the thing it eventually reaches.
 *
 * A path counts as a list of its edges here, so `size(p)` on a bound path means the
 * same as `path_length(p)`. That saves a reader from having to remember which of the
 * two shapes a pattern happened to give them.
 *
 * @visibility App\Gql
 */
final class ListFunctions
{
    /**
     * Returns how many values a list holds.
     *
     * @param Datum $value The list, or a path read as its edges
     *
     * @example The length of a chain of calls is the size of what bound it
     *     $edges = new \App\Gql\Datum\ListDatum([new \App\Gql\Datum\IntegerDatum(1), new \App\Gql\Datum\IntegerDatum(2)]);
     *     \App\Gql\Invocation\ListFunctions::size($edges)->toText() // => '2'
     * @example Something that is not there has no size
     *     \App\Gql\Invocation\ListFunctions::size(new \App\Gql\Datum\NullDatum())->kind() // => \App\Gql\Datum\DatumKind::Null
     *
     * @return Datum The size, or the absence of one
     *
     * @throws GqlException If the value is not a list
     */
    public static function size(Datum $value): Datum
    {
        if ($value->kind() === DatumKind::Null) {
            return new NullDatum();
        }

        return new IntegerDatum(count(self::items($value)));
    }

    /**
     * Returns the first values of a list, up to a given number of them.
     *
     * @param Datum $value The list
     * @param Datum $most  How many values to keep at most
     *
     * @example A list is cut down to the length asked for
     *     $rows = new \App\Gql\Datum\ListDatum([new \App\Gql\Datum\IntegerDatum(1), new \App\Gql\Datum\IntegerDatum(2)]);
     *     \App\Gql\Invocation\ListFunctions::trimTo($rows, new \App\Gql\Datum\IntegerDatum(1))->toText() // => '[1]'
     *
     * @return Datum The shortened list, or the absence of one
     *
     * @throws GqlException If the values are not a list and a whole number
     */
    public static function trimTo(Datum $value, Datum $most): Datum
    {
        if ($value->kind() === DatumKind::Null || $most->kind() === DatumKind::Null) {
            return new NullDatum();
        }
        if (!$most instanceof IntegerDatum) {
            throw GqlException::because(
                StatusCode::InvalidType,
                sprintf('a whole number was expected, and a %s was given', $most->kind()->typeName()),
            );
        }

        return new ListDatum(array_slice(self::items($value), 0, max($most->value, 0)));
    }

    /**
     * Reads a value as the values of a list.
     *
     * A path reads as its edges, which is what makes the length of a path and the
     * size of what a variable-length pattern bound the same question.
     *
     * @param Datum $value The list, or a path read as its edges
     *
     * @example A list reads as the values it holds
     *     \App\Gql\Invocation\ListFunctions::items(new \App\Gql\Datum\ListDatum([new \App\Gql\Datum\IntegerDatum(1)])) // => [new \App\Gql\Datum\IntegerDatum(1)]
     * @example Anything else is reported under the status GQL gives it
     *     \App\Gql\Invocation\ListFunctions::items(new \App\Gql\Datum\StringDatum('a')) // throws \App\Gql\GqlException: invalid value type
     *
     * @return list<Datum> The values
     *
     * @throws GqlException If the value is not a list
     */
    public static function items(Datum $value): array
    {
        if ($value instanceof ListDatum) {
            return $value->items;
        }
        if ($value instanceof PathDatum) {
            return $value->edges();
        }

        throw GqlException::because(
            StatusCode::InvalidType,
            sprintf('a list was expected, and a %s was given', $value->kind()->typeName()),
        );
    }
}
