<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Pattern;

/**
 * Which way a pattern crosses an edge.
 *
 * Direction is the whole point of a dependency graph. `(a)-[:methodCall]->(b)` asks
 * what `a` calls; `(a)<-[:methodCall]-(b)` asks what calls `a`; and the undirected
 * form asks which methods `a` is involved in a call with, either way round. Those are
 * three different questions about the same edges, and a query language that could
 * only ask one of them would answer half of what impact analysis needs.
 *
 * A pattern written undirected matches an edge twice when both its ends match the
 * same node pattern, which is GQL's rule and is worth knowing: a self-call shows up
 * once in each direction.
 *
 * GQL writes seven edge patterns, and three of them — `~[ ]~`, `<~[ ]~` and `~[ ]~>` —
 * are about undirected edges. peq reads source code, and a relation in source code
 * always points one way, so the graph has no undirected edge: `~[ ]~` matches nothing,
 * and the two mixed forms match exactly what their directed half does. The remaining
 * two, `-[ ]-` and `<-[ ]->`, differ only on undirected edges, so both are `Either`.
 */
enum EdgeDirection
{
    /** The pattern crosses the edge the way the edge points */
    case Along;

    /** The pattern crosses the edge against the way it points */
    case Against;

    /** The pattern crosses the edge whichever way it points */
    case Either;

    /** The pattern crosses only an edge that points neither way */
    case Undirected;

    /**
     * Writes the direction the way a pattern writes it, around a given label.
     *
     * @param string $inside What is written between the brackets
     *
     * @example A pattern that follows an edge is written pointing forward
     *     \App\Gql\Syntax\Pattern\EdgeDirection::Along->spelling(':calls') // => '-[:calls]->'
     * @example One that reads an edge backwards is written pointing back
     *     \App\Gql\Syntax\Pattern\EdgeDirection::Against->spelling(':calls') // => '<-[:calls]-'
     * @example One that does not care is written without a point
     *     \App\Gql\Syntax\Pattern\EdgeDirection::Either->spelling('') // => '-[]-'
     *
     * @return string The edge pattern, as a query writes it
     */
    public function spelling(string $inside): string
    {
        return match ($this) {
            self::Along => '-['.$inside.']->',
            self::Against => '<-['.$inside.']-',
            self::Either => '-['.$inside.']-',
            self::Undirected => '~['.$inside.']~',
        };
    }
}
