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
 * GQL's functions over the graph itself.
 *
 * These take apart what a pattern bound. A path is the most useful thing a query about
 * code can return — not "these two symbols are connected" but "here is the chain
 * between them" — and these are the two ways GQL gets at its parts: everything it is
 * made of, and how long it is.
 *
 * Other graph languages also offer a `nodes` and an `edges` that split those elements
 * in two, and a `labels` that reads an element's labels back. ISO/IEC 39075 defines
 * neither, so peq has neither: a pattern is how a query asks about labels, and
 * `elements` is what the standard gives for the rest.
 *
 * @visibility App\Gql
 */
final class GraphFunctions
{
    /**
     * Returns everything a path is made of, in the order it was walked.
     *
     * @param Datum $value The path
     *
     * @example A path of one relation is made of three things
     *     $path = \App\Gql\Datum\PathDatum::at(new \App\Gql\Datum\NodeDatum('a'))->continuedBy(new \App\Gql\Datum\EdgeDatum('e', [], [], 'a', 'b'), new \App\Gql\Datum\NodeDatum('b'));
     *     \App\Gql\Invocation\GraphFunctions::elements($path)->kind() // => \App\Gql\Datum\DatumKind::ListOf
     *
     * @return Datum The symbols and relations, or the absence of them
     *
     * @throws GqlException If the value is not a path
     */
    public static function elements(Datum $value): Datum
    {
        if ($value->kind() === DatumKind::Null) {
            return new NullDatum();
        }

        return new ListDatum(self::path($value)->elements);
    }

    /**
     * Returns how many relations a path crosses.
     *
     * @param Datum $value The path
     *
     * @example A path that crosses nothing has no length
     *     \App\Gql\Invocation\GraphFunctions::pathLength(\App\Gql\Datum\PathDatum::at(new \App\Gql\Datum\NodeDatum('a')))->toText() // => '0'
     *
     * @return Datum The length, or the absence of one
     *
     * @throws GqlException If the value is not a path
     */
    public static function pathLength(Datum $value): Datum
    {
        if ($value->kind() === DatumKind::Null) {
            return new NullDatum();
        }

        return new IntegerDatum(self::path($value)->length());
    }

    /**
     * Reads a value as a path.
     *
     * @param Datum $value The value
     *
     * @example A path reads as itself
     *     \App\Gql\Invocation\GraphFunctions::path(\App\Gql\Datum\PathDatum::at(new \App\Gql\Datum\NodeDatum('a')))->length() // => 0
     * @example Anything else is reported under the status GQL gives it
     *     \App\Gql\Invocation\GraphFunctions::path(new \App\Gql\Datum\StringDatum('a')) // throws \App\Gql\GqlException: invalid value type
     *
     * @return PathDatum The path
     *
     * @throws GqlException If the value is not a path
     */
    public static function path(Datum $value): PathDatum
    {
        if ($value instanceof PathDatum) {
            return $value;
        }

        throw GqlException::because(
            StatusCode::InvalidType,
            sprintf('a path was expected, and a %s was given', $value->kind()->typeName()),
        );
    }
}
