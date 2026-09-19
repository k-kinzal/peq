<?php

declare(strict_types=1);

namespace App\Gql\Invocation;

use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\PathDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\GqlException;
use App\Gql\StatusCode;

/**
 * GQL's functions over the graph itself.
 *
 * These take apart what a pattern bound. A path is the most useful thing a query
 * about code can return — not "these two symbols are connected" but "here is the
 * chain between them" — and these are how a reader gets at its parts: the symbols it
 * passes through, the relations it crosses, how long it is.
 *
 * `labels` completes the picture from the other direction. A pattern selects by
 * label; `labels` is how a result says which ones a symbol actually turned out to
 * carry, which is what an agent needs in order to ask a better question next.
 *
 * @visibility App\Gql
 */
final class GraphFunctions
{
    /**
     * Returns the symbols a path passes through, in order.
     *
     * @param Datum $value The path
     *
     * @example A path of one relation passes through two symbols
     *     $path = \App\Gql\Datum\PathDatum::at(new \App\Gql\Datum\NodeDatum('a'))->continuedBy(new \App\Gql\Datum\EdgeDatum('e', [], [], 'a', 'b'), new \App\Gql\Datum\NodeDatum('b'));
     *     \App\Gql\Invocation\GraphFunctions::nodes($path)->toText() // => '[a, b]'
     *
     * @return Datum The symbols, or the absence of them
     *
     * @throws GqlException If the value is not a path
     */
    public static function nodes(Datum $value): Datum
    {
        if ($value->kind() === DatumKind::Null) {
            return new NullDatum();
        }

        return new ListDatum(self::path($value)->nodes());
    }

    /**
     * Returns the relations a path crosses, in order.
     *
     * @param Datum $value The path
     *
     * @example The relations of a path are what a reader goes and looks at
     *     $path = \App\Gql\Datum\PathDatum::at(new \App\Gql\Datum\NodeDatum('a'))->continuedBy(new \App\Gql\Datum\EdgeDatum('e', ['calls'], [], 'a', 'b'), new \App\Gql\Datum\NodeDatum('b'));
     *     \App\Gql\Invocation\GraphFunctions::edges($path)->toText() // => '[a -[calls]-> b]'
     *
     * @return Datum The relations, or the absence of them
     *
     * @throws GqlException If the value is not a path
     */
    public static function edges(Datum $value): Datum
    {
        if ($value->kind() === DatumKind::Null) {
            return new NullDatum();
        }

        return new ListDatum(self::path($value)->edges());
    }

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
     * Returns the labels a symbol or a relation carries.
     *
     * @param Datum $value The symbol or the relation
     *
     * @example A symbol reports the labels a pattern could have selected it by
     *     \App\Gql\Invocation\GraphFunctions::labels(new \App\Gql\Datum\NodeDatum('a', ['Method', 'Callable']))->toText() // => '[Method, Callable]'
     *
     * @return Datum The labels, or the absence of them
     *
     * @throws GqlException If the value is neither a symbol nor a relation
     */
    public static function labels(Datum $value): Datum
    {
        if ($value->kind() === DatumKind::Null) {
            return new NullDatum();
        }

        $labels = match (true) {
            $value instanceof NodeDatum => $value->labels,
            $value instanceof EdgeDatum => $value->labels,
            default => null,
        };
        if ($labels === null) {
            throw GqlException::because(
                StatusCode::InvalidType,
                sprintf('a node or an edge was expected, and a %s was given', $value->kind()->typeName()),
            );
        }

        return new ListDatum(array_map(static fn (string $label): Datum => new StringDatum($label), $labels));
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
