<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\IdentifierAssert;
use App\Analyzer\Graph\Node\TraitNode;
use App\Analyzer\Graph\NodeId;

/**
 * Unique identifier for a trait node in the dependency graph.
 *
 * Represents a fully qualified trait identifier consisting of a namespace
 * and trait name. This ID uniquely identifies a PHP trait within
 * the analyzed codebase.
 *
 * @implements NodeId<TraitNode>
 *
 * @property string $fullQualifiedName Alias for fullQualifiedName()
 */
final class TraitNodeId implements NodeId
{
    use IdentifierAssert;

    /**
     * @param string $namespace The namespace of the trait (must be a valid PHP namespace)
     * @param string $traitName The trait name (must be a valid PHP identifier)
     */
    private readonly string $stringValue;

    public function __construct(
        public readonly string $namespace,
        public readonly string $traitName,
    ) {
        if ($namespace !== '') {
            self::assertNamespace($namespace);
        }
        self::assertIdentifier($traitName);
        $this->stringValue = $namespace === '' ? $traitName : $namespace.'\\'.$traitName;
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
     * Returns the fully qualified trait name.
     *
     * Combines namespace and trait name with a backslash separator
     * (e.g., "App\Traits\Timestampable").
     *
     * @return string The fully qualified trait name
     */
    public function fullQualifiedName(): string
    {
        return $this->stringValue;
    }

    /**
     * Returns the string representation of this identifier.
     *
     * @return string The fully qualified trait name
     */
    public function toString(): string
    {
        return $this->stringValue;
    }
}
