<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\Node\UnknownNode;
use App\Analyzer\Graph\NodeId;

/**
 * Unique identifier for an unknown node in the dependency graph.
 *
 * Represents an identifier for nodes that could not be resolved or classified
 * into a specific type during analysis. This is used as a placeholder for
 * unresolved dependencies.
 *
 * @implements NodeId<UnknownNode>
 */
final class UnknownNodeId implements NodeId
{
    /**
     * @param string $name The name of the unknown node (must not be empty)
     */
    public function __construct(
        public readonly string $name,
    ) {
        assert($name !== '');
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Returns the string representation of this identifier.
     *
     * @return string The name of the unknown node
     */
    public function toString(): string
    {
        return $this->name;
    }
}
