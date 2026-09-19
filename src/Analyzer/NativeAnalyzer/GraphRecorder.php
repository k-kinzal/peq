<?php

declare(strict_types=1);

namespace App\Analyzer\NativeAnalyzer;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;

/**
 * Collects what a walk finds, and turns it into a graph once the walk is over.
 *
 * A walk meets a symbol and a relation to it in whatever order the source happens to
 * be written, and a graph cannot be built in that order: recording a relation before
 * the symbol it points at would leave the graph holding a placeholder where it could
 * have held the symbol itself. Everything found is therefore collected first, and the
 * graph is built from it in two passes, symbols before relations.
 *
 * @visibility namespace
 */
final class GraphRecorder
{
    /**
     * @var list<Edge|Node> Everything the walk has found so far
     */
    private array $found = [];

    /**
     * Records what one step of the walk found.
     *
     * @param list<Edge|Node> $symbols The symbols and relations it found
     */
    public function record(array $symbols): void
    {
        foreach ($symbols as $symbol) {
            $this->found[] = $symbol;
        }
    }

    /**
     * Builds the graph of everything recorded.
     *
     * Symbols are recorded before relations, so a symbol that was actually analysed
     * is recorded with everything known about it rather than as the placeholder a
     * relation would otherwise have created for it.
     *
     * @return Graph The graph the walk describes
     */
    public function graph(): Graph
    {
        $graph = new Graph();
        foreach ($this->found as $symbol) {
            if ($symbol instanceof Node) {
                $graph->addNode($symbol);
            }
        }
        foreach ($this->found as $symbol) {
            if ($symbol instanceof Edge) {
                $graph->addEdge($symbol);
            }
        }

        return $graph;
    }
}
