<?php

declare(strict_types=1);

namespace App\Analyzer\Graph;

/**
 * Represents an edge (relationship) between two nodes in the dependency graph.
 *
 * An edge connects two nodes and represents a specific type of relationship between
 * PHP code elements, such as a method call, class extension, or type declaration.
 * Each edge includes metadata about where the relationship is defined in the source code.
 *
 * Every edge is invertible and inversion is total: `$edge->invert()->invert()`
 * always yields an edge equivalent to `$edge`. Edges written in source code invert
 * into an InverseEdge that remembers them, and an InverseEdge inverts back into
 * exactly the edge it was derived from.
 */
interface Edge
{
    /**
     * Returns the identifier of the node this edge starts at.
     *
     * @return NodeId<Node> The source node identifier
     */
    public function from(): NodeId;

    /**
     * Returns the identifier of the node this edge points at.
     *
     * @return NodeId<Node> The target node identifier
     */
    public function to(): NodeId;

    /**
     * Returns the kind of relationship this edge represents.
     *
     * @return EdgeKind The kind of this edge
     */
    public function kind(): EdgeKind;

    /**
     * Returns where in the source code this relationship is written.
     *
     * @return FileMeta The file, line and column of the relationship
     */
    public function meta(): FileMeta;

    /**
     * Returns this edge read in the opposite direction.
     *
     * @return Edge The same relationship with source and target exchanged
     */
    public function invert(): self;
}
