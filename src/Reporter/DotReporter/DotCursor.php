<?php

declare(strict_types=1);

namespace App\Reporter\DotReporter;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Reporter\Expansion;
use App\Reporter\Traversal;

/**
 * The part of the graph one digraph report covers.
 *
 * A tree has to pick one path to each symbol and mark the others as repeats; a
 * digraph does not, because an arrow arriving twice at the same box is exactly what
 * a picture of a graph is for. So this cursor keeps only the set of symbols the walk
 * reached, and then reads back out of the graph every relation that runs between two
 * of them.
 *
 * That makes the picture the induced subgraph of the walk rather than a drawing of a
 * tree: the nodes are the ones the walk reported, and a cycle among them shows as the
 * loop it is instead of a branch that stops with a word next to it.
 *
 * @visibility namespace
 */
final class DotCursor
{
    /**
     * @var array<string, Node> The nodes the walk reached, keyed by identifier, in the order it reached them
     */
    private array $reached = [];

    /**
     * How far this report has already expanded the graph.
     */
    private readonly Expansion $expansion;

    /**
     * @param Graph     $graph     The graph being reported on
     * @param Traversal $traversal The traversal whose direction decides which relations are drawn
     * @param null|int  $level     Deepest level to report, or null for the whole graph
     */
    public function __construct(
        private readonly Graph $graph,
        private readonly Traversal $traversal,
        ?int $level = null,
    ) {
        $this->expansion = new Expansion($level);
    }

    /**
     * Records one node and reports whether the walk should continue below it.
     *
     * @param Node $node  The node the traversal reached
     * @param int  $depth How far below the root symbol it sits
     *
     * @return bool True when the traversal should descend into this node
     */
    public function visit(Node $node, int $depth): bool
    {
        $continuation = $this->expansion->reach($node, $depth);
        if (!$continuation->reported()) {
            return false;
        }

        $this->reached[$node->id()->toString()] = $node;

        return $continuation->descends();
    }

    /**
     * Returns the nodes the walk reached.
     *
     * @return list<Node> The nodes, in the order the walk reached them
     */
    public function nodes(): array
    {
        return array_values($this->reached);
    }

    /**
     * Returns every relation the walk's direction reads between two reached nodes.
     *
     * A relation pointing out of the covered part of the graph is left out: it would
     * draw an arrow to a box the picture does not contain, which is how a level bound
     * would otherwise leak into the output as a dangling edge.
     *
     * @return list<Edge> The relations, grouped by the node they read from
     */
    public function edges(): array
    {
        $edges = [];
        foreach ($this->reached as $node) {
            foreach ($this->graph->edges($node->id()) as $edge) {
                if ($edge->kind()->direction() === $this->traversal->direction()
                    && isset($this->reached[$edge->to()->toString()])
                ) {
                    $edges[] = $edge;
                }
            }
        }

        return $edges;
    }
}
