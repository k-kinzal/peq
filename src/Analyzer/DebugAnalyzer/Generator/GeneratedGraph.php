<?php

declare(strict_types=1);

namespace App\Analyzer\DebugAnalyzer\Generator;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use Closure;

/**
 * A generated graph together with the node it was grown from.
 *
 * Generating a symbol produces both a graph and the node at its root, and the caller
 * needs the node: it is the endpoint of the edge that attaches the symbol to its
 * parent. Returning only the graph would force the caller to look the node back up
 * and re-establish its type, which is how a generator ends up asserting facts it
 * already had. Carrying the node keeps that type from being lost, so relating a
 * symbol to one it declares or uses is a typed call rather than a narrowed lookup.
 *
 * @template-covariant TNode of Node
 *
 * @visibility parent
 */
final class GeneratedGraph
{
    /**
     * @param Graph $graph The generated graph
     * @param TNode $root  The node the generated graph was grown from
     */
    public function __construct(
        public readonly Graph $graph,
        public readonly Node $root,
    ) {}

    /**
     * Starts a graph holding nothing but the given node.
     *
     * @template TRoot of Node
     *
     * @param TRoot $root The node to grow the graph from
     *
     * @return self<TRoot> A graph holding that node alone
     */
    public static function rootedAt(Node $root): self
    {
        $graph = new Graph();
        $graph->addNode($root);

        return new self($graph, $root);
    }

    /**
     * Grows this graph by another one and records the relation between their roots.
     *
     * Both roots arrive at the relation with the type they were generated as, so the
     * edge constructor that only accepts certain node types can be called directly.
     *
     * @template TChild of Node
     *
     * @param self<TChild>                 $child   The generated graph to grow this one by
     * @param Closure(TNode, TChild): Edge $relates How the two roots are related
     *
     * @return self<TNode> A graph holding both, with the relation recorded
     */
    public function relatedTo(self $child, Closure $relates): self
    {
        $graph = $this->graph->merge($child->graph);
        $graph->addEdge($relates($this->root, $child->root));

        return new self($graph, $this->root);
    }
}
