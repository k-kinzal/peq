<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\IdentifierAssert;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\NodeId;

/**
 * Unique identifier for a function node in the dependency graph.
 *
 * Represents a fully qualified function identifier consisting of a namespace
 * and function name. This ID uniquely identifies a global function within
 * the analyzed codebase.
 *
 * @implements NodeId<FunctionNode>
 *
 * @property string $fullQualifiedName Alias for fullQualifiedName()
 */
final readonly class FunctionNodeId implements NodeId
{
    use IdentifierAssert;

    /**
     * @param string $namespace    The namespace of the function (must be a valid PHP namespace)
     * @param string $functionName The function name (must be a valid PHP identifier)
     */
    public function __construct(
        public string $namespace,
        public string $functionName,
    ) {
        if ($namespace !== '') {
            self::assertNamespace($namespace);
        }
        self::assertIdentifier($functionName);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'fullQualifiedName' => $this->fullQualifiedName(),
            default => throw new \LogicException("Undefined property: {$name}"),
        };
    }

    /**
     * Returns the fully qualified function name.
     *
     * Combines namespace and function name with a backslash separator
     * (e.g., "App\Helpers\formatDate").
     *
     * @return string The fully qualified function name
     */
    public function fullQualifiedName(): string
    {
        if ($this->namespace === '') {
            return $this->functionName;
        }

        return $this->namespace.'\\'.$this->functionName;
    }

    /**
     * Returns the string representation of this identifier.
     *
     * @return string The fully qualified function name
     */
    public function toString(): string
    {
        return $this->fullQualifiedName();
    }
}
