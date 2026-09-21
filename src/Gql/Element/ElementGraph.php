<?php

declare(strict_types=1);

namespace App\Gql\Element;

use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\NodeDatum;

/**
 * The dependency graph as a query sees it: labelled elements, indexed both ways.
 *
 * A pattern is matched by starting somewhere and following edges, and it follows them
 * in whichever direction it was drawn. Both directions therefore have to be as cheap
 * as each other, which is why the edges are indexed by where they leave and by where
 * they arrive rather than being scanned.
 *
 * Only the relations source code actually writes are here. peq's own graph records
 * every relation twice so that a tree can be walked either way; a query has no use
 * for that, because `<-[:methodCall]-` already says "read this the other way". Keeping
 * the derived readings out is what stops an undirected pattern from matching every
 * relation twice.
 *
 * @visibility App
 */
final readonly class ElementGraph
{
    /**
     * @param array<string, NodeDatum>       $nodes    Every symbol, by what identifies it
     * @param array<string, list<EdgeDatum>> $leaving  The relations leaving each symbol, by what identifies it
     * @param array<string, list<EdgeDatum>> $arriving The relations arriving at each symbol, by what identifies it
     */
    public function __construct(
        private array $nodes,
        private array $leaving,
        private array $arriving,
    ) {}

    /**
     * Returns every symbol in the graph.
     *
     * A pattern that starts from nothing has to start somewhere, and that somewhere
     * is here: every symbol is a candidate until a label or a property rules it out.
     *
     * @example An empty graph holds no symbol
     *     (new \App\Gql\Element\ElementGraph([], [], []))->nodes() // => []
     *
     * @return list<NodeDatum> The symbols
     */
    public function nodes(): array
    {
        return array_values($this->nodes);
    }

    /**
     * Returns one symbol by what identifies it.
     *
     * @param string $id What identifies the symbol
     *
     * @example A symbol the graph does not hold is not found
     *     (new \App\Gql\Element\ElementGraph([], [], []))->node('App\\Missing') // => null
     *
     * @return null|NodeDatum The symbol, or null when the graph holds none
     */
    public function node(string $id): ?NodeDatum
    {
        return $this->nodes[$id] ?? null;
    }

    /**
     * Returns the relations leaving a symbol.
     *
     * @param string $id What identifies the symbol
     *
     * @example A symbol the graph does not hold leaves nothing
     *     (new \App\Gql\Element\ElementGraph([], [], []))->leaving('App\\Missing') // => []
     *
     * @return list<EdgeDatum> The relations written from it
     */
    public function leaving(string $id): array
    {
        return $this->leaving[$id] ?? [];
    }

    /**
     * Returns every relation between two of a given set of symbols.
     *
     * A drawing of what a query found is worth more than a list of it, and what makes
     * it a drawing is the arrows. A query that matched a pattern binds the symbols it
     * was written about and usually not the relations between them, so those are read
     * back out of the graph here — the same induced subgraph an inspection draws.
     *
     * @param array<string, true> $wanted What identifies each symbol, as a set
     *
     * @example A set with nothing in it is joined by nothing
     *     (new \App\Gql\Element\ElementGraph([], [], []))->between([]) // => []
     *
     * @return list<EdgeDatum> The relations between them
     */
    public function between(array $wanted): array
    {
        $found = [];
        foreach (array_keys($wanted) as $id) {
            foreach ($this->leaving($id) as $edge) {
                if (isset($wanted[$edge->target])) {
                    $found[] = $edge;
                }
            }
        }

        return $found;
    }

    /**
     * Returns the relations arriving at a symbol.
     *
     * @param string $id What identifies the symbol
     *
     * @example A symbol the graph does not hold receives nothing
     *     (new \App\Gql\Element\ElementGraph([], [], []))->arriving('App\\Missing') // => []
     *
     * @return list<EdgeDatum> The relations written towards it
     */
    public function arriving(string $id): array
    {
        return $this->arriving[$id] ?? [];
    }
}
