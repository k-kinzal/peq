<?php

declare(strict_types=1);

namespace App\Reporter\JsonReporter;

use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId;
use App\Reporter\Expansion;
use App\Reporter\Traversal;

/**
 * The document one JSON report is accumulating as the walk goes on.
 *
 * The walk is depth first, and a JSON document written while it runs would have to
 * be closed in the same order — which means holding open arrays for every level and
 * hoping the walk ends the way the nesting expects. This cursor records a flat entry
 * per node instead, each one carrying the depth it sits at and the symbol it hangs
 * under, so the tree is recoverable without the document ever nesting deeper than the
 * three levels it is written with.
 *
 * Each entry also names the relations that led to the node. That is the question
 * impact analysis actually asks — not only what is affected, but through what — and
 * it is the one thing the tree cannot show without becoming unreadable.
 *
 * @phpstan-type ReportedFile array{path: string, line: int, column: int}
 * @phpstan-type ReportedNode array{id: string, kind: string, resolved: bool, depth: int, parent: null|string, relations: list<string>, truncated: null|string, file: null|ReportedFile}
 *
 * @visibility namespace
 */
final class JsonCursor
{
    /**
     * @var list<ReportedNode> The entry written for each node the walk reached, in the order it reached them
     */
    private array $reached = [];

    /**
     * @var array<int, NodeId<Node>> The node reported last at each depth, which is the parent of the next one below it
     */
    private array $parents = [];

    /**
     * How far this report has already expanded the graph.
     */
    private readonly Expansion $expansion;

    /**
     * @param Graph     $graph     The graph being reported on
     * @param Traversal $traversal The traversal whose direction decides which relations count
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

        $parent = $depth > 0 ? ($this->parents[$depth - 1] ?? null) : null;
        $meta = $node->meta();

        $this->reached[] = [
            'id' => $node->id()->toString(),
            'kind' => $node->kind()->value,
            'resolved' => $node->resolved(),
            'depth' => $depth,
            'parent' => $parent?->toString(),
            'relations' => $parent === null ? [] : $this->relations($parent, $node->id()),
            'truncated' => $continuation->marker(),
            'file' => $meta === null ? null : ['path' => $meta->path, 'line' => $meta->line, 'column' => $meta->column],
        ];

        $this->parents[$depth] = $node->id();

        return $continuation->descends();
    }

    /**
     * Names every relation that reads from one symbol to another in this direction.
     *
     * Two symbols can be related more than once — a method that both instantiates a
     * class and names it as a return type — and which of those relations a walk
     * happened to follow is an accident of edge order. All of them are reported.
     *
     * @param NodeId<Node> $from The symbol the relations read from
     * @param NodeId<Node> $to   The symbol they read to
     *
     * @return list<string> The kinds of those relations, as they are written
     */
    public function relations(NodeId $from, NodeId $to): array
    {
        $target = $to->toString();

        $relations = [];
        foreach ($this->graph->edges($from) as $edge) {
            if ($edge->kind()->direction() === $this->traversal->direction() && $edge->to()->toString() === $target) {
                $relations[] = $edge->kind()->value;
            }
        }

        return $relations;
    }

    /**
     * Returns the entry written for each node the walk reached.
     *
     * @return list<ReportedNode> The entries, in the order the walk reached them
     */
    public function reached(): array
    {
        return $this->reached;
    }
}
