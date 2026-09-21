<?php

declare(strict_types=1);

namespace App\Analyzer\Graph;

use Override;

/**
 * A relation that source code writes, holding its endpoints and where it is written.
 *
 * Every relation the analyzer reads out of a file is the same triple — a node it
 * starts at, a node it points at and the place the two are named together — and
 * differs only in what kind of relation it is and how it reads backwards. That
 * triple is held here so each kind is left saying only the two things that make it
 * that kind, and so no kind can be written that forgets to record one of the three.
 *
 * Edges are constructed from nodes because that is what the analyzer has at hand and
 * because the node types constrain which relations are expressible. The Edge contract
 * exposes identifiers rather than nodes, so the nodes stay behind this class: a
 * consumer that needs the node asks the graph for it by identifier.
 *
 * The counterpart is InverseEdge, which is not authored but derived: it carries the
 * authored edge it was read backwards from instead of endpoints of its own.
 */
abstract readonly class AuthoredEdge implements Edge
{
    /**
     * @param Node     $fromNode The node this relation starts at
     * @param Node     $toNode   The node this relation points at
     * @param FileMeta $meta     Where in the source code the relation is written
     */
    protected function __construct(
        private Node $fromNode,
        private Node $toNode,
        private FileMeta $meta,
    ) {}

    /**
     * Returns the identifier of the node this edge starts at.
     *
     * @return NodeId<Node> The source node identifier
     */
    #[Override]
    public function from(): NodeId
    {
        return $this->fromNode->id();
    }

    /**
     * Returns the identifier of the node this edge points at.
     *
     * @return NodeId<Node> The target node identifier
     */
    #[Override]
    public function to(): NodeId
    {
        return $this->toNode->id();
    }

    /**
     * Returns where in the source code this relation is written.
     *
     * @return FileMeta The file, line and column of the relation
     */
    #[Override]
    public function meta(): FileMeta
    {
        return $this->meta;
    }
}
