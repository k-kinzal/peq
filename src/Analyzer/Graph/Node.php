<?php

declare(strict_types=1);

namespace App\Analyzer\Graph;

use App\Analyzer\Graph\Declaration\SymbolDeclaration;

/**
 * Represents a node in the dependency graph.
 *
 * A node represents a PHP code element (class, method, function, etc.) in the
 * dependency graph. All nodes have a unique identifier, kind, optional metadata,
 * resolution status, and whatever the source declares about them.
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

    /**
     * Returns what the source declares about this symbol, if analysis read it.
     *
     * A node stands for a symbol whether or not the analysed sources declare it. One
     * that was only ever referred to — a class from a dependency, a method reached
     * past the analysed boundary — has a name and nothing else, and says so by
     * answering null rather than by answering a declaration that is empty.
     *
     * @return null|SymbolDeclaration What the declaration says, or null when none was read
     */
    public function declaration(): ?SymbolDeclaration;
}
