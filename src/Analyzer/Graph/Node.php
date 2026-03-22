<?php

declare(strict_types=1);

namespace App\Analyzer\Graph;

/**
 * Represents a node in the dependency graph.
 *
 * A node represents a PHP code element (class, method, function, etc.) in the
 * dependency graph. All nodes have a unique identifier, kind, optional metadata,
 * and resolution status.
 */
interface Node
{
    /**
     * Returns the unique identifier for this node.
     *
     * @return NodeId<Node> The unique identifier for this node
     */
    public function id(): NodeId;

    /**
     * Returns the kind of this node.
     *
     * @return NodeKind The node's kind (e.g., class, method, function)
     */
    public function kind(): NodeKind;

    /**
     * Indicates whether this node has been fully resolved during analysis.
     *
     * @return bool True if resolved, false otherwise
     */
    public function resolved(): bool;

    /**
     * Returns the file location metadata for this node, if available.
     *
     * @return null|FileMeta The file metadata or null if not available
     */
    public function meta(): ?FileMeta;
}
