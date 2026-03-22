<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\IdentifierAssert;
use App\Analyzer\Graph\Node\BuiltinNode;
use App\Analyzer\Graph\NodeId;

/**
 * Unique identifier for a builtin type node in the dependency graph.
 *
 * Represents a fully qualified identifier for PHP builtin types (e.g., string, int, array).
 * This ID uniquely identifies a PHP builtin type within the analyzed codebase.
 *
 * @implements NodeId<BuiltinNode>
 *
 * @property string $fullQualifiedName Alias for fullQualifiedName()
 */
final readonly class BuiltinNodeId implements NodeId
{
    use IdentifierAssert;

    /**
     * @param string $namespace The namespace of the builtin type (must be a valid PHP namespace)
     * @param string $name      The builtin type name (must be a valid PHP identifier)
     */
    public function __construct(
        public string $namespace,
        public string $name,
    ) {
        self::assertNamespace($namespace);
        self::assertIdentifier($name);
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
     * Returns the fully qualified builtin type name.
     *
     * Combines namespace and builtin type name with a backslash separator.
     *
     * @return string The fully qualified builtin type name
     */
    public function fullQualifiedName(): string
    {
        if ($this->namespace === '') {
            return $this->name;
        }

        return $this->namespace.'\\'.$this->name;
    }

    /**
     * Returns the string representation of this identifier.
     *
     * @return string The fully qualified builtin type name
     */
    public function toString(): string
    {
        return $this->fullQualifiedName();
    }
}
