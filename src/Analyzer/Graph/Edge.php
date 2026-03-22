<?php

declare(strict_types=1);

namespace App\Analyzer\Graph;

/**
 * Represents an edge (relationship) between two nodes in the dependency graph.
 *
 * An edge connects two nodes and represents a specific type of relationship between
 * PHP code elements, such as a method call, class extension, or type declaration.
 * Each edge includes metadata about where the relationship is defined in the source code.
 */
interface Edge
{
    /**
     * @return NodeId<Node>
     */
    public function from(): NodeId;

    /**
     * @return NodeId<Node>
     */
    public function to(): NodeId;

    public function kind(): EdgeKind;

    public function meta(): FileMeta;

    /**
     * Creates an inverted edge with swapped from/to nodes and inverted edge kind.
     *
     * @return self A new Edge with inverted direction
     */
    public function invert(): self;
}
