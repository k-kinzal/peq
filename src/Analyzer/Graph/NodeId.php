<?php

declare(strict_types=1);

namespace App\Analyzer\Graph;

/**
 * Represents a unique identifier for a node in the dependency graph.
 *
 * This interface defines the contract for all node identifiers. Concrete implementations
 * include ClassNodeId, MethodNodeId, FunctionNodeId, etc., each with type-specific properties.
 *
 * @template-covariant T of Node
 */
interface NodeId
{
    /**
     * Returns the string representation of this identifier.
     *
     * @return string The string representation (e.g., fully qualified name)
     */
    public function toString(): string;
}
