<?php

declare(strict_types=1);

namespace App\Gql\Matching;

use App\Gql\Datum\EdgeDatum;
use App\Gql\Element\ElementGraph;
use App\Gql\Syntax\Pattern\EdgeDirection;

/**
 * The relations a pattern can cross from where it is standing.
 *
 * Which relations those are depends entirely on which way the pattern was drawn, and
 * that is the difference between the two questions impact analysis asks. Standing at
 * a method, `-[:methodCall]->` leads to what it calls and `<-[:methodCall]-` leads to
 * what calls it; the same relations, read from opposite ends.
 *
 * A pattern drawn without a direction crosses both, which means a relation whose two
 * ends are the same symbol is crossed twice — once each way. That is GQL's rule, and
 * on a graph of code it is the honest one: a method that calls itself is involved in
 * that call as caller and as called.
 *
 * @visibility App\Gql
 */
final readonly class EdgeTraversal
{
    /**
     * @param EdgeDatum $edge  The relation that can be crossed
     * @param string    $other What identifies the symbol at the other end of it
     */
    public function __construct(
        public EdgeDatum $edge,
        public string $other,
    ) {}

    /**
     * Returns the relations a pattern can cross from a symbol.
     *
     * @param ElementGraph  $graph     The graph being matched against
     * @param string        $from      What identifies the symbol being stood at
     * @param EdgeDirection $direction Which way the pattern crosses a relation
     *
     * @example A pattern drawn forwards crosses the relations that leave
     *     $edge = new \App\Gql\Datum\EdgeDatum('e', [], [], 'a', 'b');
     *     $graph = new \App\Gql\Element\ElementGraph([], ['a' => [$edge]], ['b' => [$edge]]);
     *     \App\Gql\Matching\EdgeTraversal::from($graph, 'a', \App\Gql\Syntax\Pattern\EdgeDirection::Along)[0]->other // => 'b'
     * @example A pattern drawn backwards crosses the relations that arrive
     *     $edge = new \App\Gql\Datum\EdgeDatum('e', [], [], 'a', 'b');
     *     $graph = new \App\Gql\Element\ElementGraph([], ['a' => [$edge]], ['b' => [$edge]]);
     *     \App\Gql\Matching\EdgeTraversal::from($graph, 'b', \App\Gql\Syntax\Pattern\EdgeDirection::Against)[0]->other // => 'a'
     *
     * @return list<self> The relations, with the symbol each one leads to
     */
    public static function from(ElementGraph $graph, string $from, EdgeDirection $direction): array
    {
        $crossings = [];
        if ($direction === EdgeDirection::Along || $direction === EdgeDirection::Either) {
            foreach ($graph->leaving($from) as $edge) {
                $crossings[] = new self($edge, $edge->target);
            }
        }
        if ($direction === EdgeDirection::Against || $direction === EdgeDirection::Either) {
            foreach ($graph->arriving($from) as $edge) {
                $crossings[] = new self($edge, $edge->origin);
            }
        }

        return $crossings;
    }
}
