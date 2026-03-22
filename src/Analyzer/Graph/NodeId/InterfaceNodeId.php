<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\IdentifierAssert;
use App\Analyzer\Graph\Node\GraphInterfaceNode;
use App\Analyzer\Graph\NodeId;

/**
 * Unique identifier for an interface node in the dependency graph.
 *
 * Represents a fully qualified interface identifier consisting of a namespace
 * and interface name. This ID uniquely identifies a PHP interface within
 * the analyzed codebase.
 *
 * @implements NodeId<GraphInterfaceNode>
 *
 * @property string $fullQualifiedName Alias for fullQualifiedName()
 */
final class InterfaceNodeId implements NodeId
{
    use IdentifierAssert;

    /**
     * @param string $namespace     The namespace of the interface (must be a valid PHP namespace)
     * @param string $interfaceName The interface name (must be a valid PHP identifier)
     */
    public function __construct(
        public readonly string $namespace,
        public readonly string $interfaceName,
    ) {
        if ($namespace !== '') {
            self::assertNamespace($namespace);
        }
        self::assertIdentifier($interfaceName);
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
     * Returns the fully qualified interface name.
     *
     * Combines namespace and interface name with a backslash separator
     * (e.g., "App\Contracts\Repository").
     *
     * @return string The fully qualified interface name
     */
    public function fullQualifiedName(): string
    {
        if ($this->namespace === '') {
            return $this->interfaceName;
        }

        return $this->namespace.'\\'.$this->interfaceName;
    }

    /**
     * Returns the string representation of this identifier.
     *
     * @return string The fully qualified interface name
     */
    public function toString(): string
    {
        return $this->fullQualifiedName();
    }
}
