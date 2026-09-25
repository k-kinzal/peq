<?php

declare(strict_types=1);

namespace App\Reporter\GraphReporter;

use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Reporter\CallOccurrences;
use App\Reporter\Diagram\Diagram;
use App\Reporter\Diagram\DiagramEdge;
use App\Reporter\Diagram\DiagramNode;
use App\Reporter\Expansion;
use App\Reporter\Traversal;

/**
 * The part of the graph one drawing covers.
 *
 * Like the digraph, a drawing keeps only the set of symbols the walk reached and
 * then reads every relation the graph holds between two of them. That is what makes
 * it a drawing of the graph rather than of the tree: a symbol two branches reach
 * appears once, and a cycle among the reached symbols is an arrow rather than a word.
 *
 * @visibility namespace
 */
final class GraphCursor
{
    /**
     * @var array<string, Node> The symbols the walk reached, by identifier, in the order it reached them
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
     * Records one symbol and reports whether the walk should continue below it.
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
     * Returns the drawing of everything the walk reached.
     *
     * A relation pointing out of the covered part is left out, the same way the
     * digraph leaves it out: an arrow to a symbol the drawing does not hold would
     * make a level bound look like a missing dependency.
     *
     * @return Diagram The drawing
     */
    public function diagram(): Diagram
    {
        $diagram = new Diagram();
        foreach ($this->reached as $node) {
            $meta = $node->meta();
            $diagram->add(new DiagramNode(
                $node->id()->toString(),
                $node->kind()->value,
                $meta === null ? null : sprintf('%s:%d', $meta->path, $meta->line),
            ));
        }

        foreach ($this->reached as $node) {
            foreach ($this->graph->edges($node->id()) as $edge) {
                if ($edge->kind()->direction() === $this->traversal->direction()
                    && isset($this->reached[$edge->to()->toString()])
                ) {
                    $diagram->relate(new DiagramEdge(
                        $edge->from()->toString(),
                        $edge->to()->toString(),
                        CallOccurrences::label($edge),
                        CallOccurrences::site($edge) !== null,
                    ));
                }
            }
        }

        return $diagram;
    }
}
