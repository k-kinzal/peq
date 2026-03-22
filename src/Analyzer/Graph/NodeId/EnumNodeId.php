<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\IdentifierAssert;
use App\Analyzer\Graph\Node\EnumNode;
use App\Analyzer\Graph\NodeId;

/**
 * Unique identifier for an enum node in the dependency graph.
 *
 * Represents a fully qualified enum identifier consisting of a namespace
 * and enum name. This ID uniquely identifies a PHP enum within
 * the analyzed codebase.
 *
 * @implements NodeId<EnumNode>
 *
 * @property string $fullQualifiedName Alias for fullQualifiedName()
 */
final readonly class EnumNodeId implements NodeId
{
    use IdentifierAssert;

    /**
     * @param string $namespace The namespace of the enum (must be a valid PHP namespace)
     * @param string $enumName  The enum name (must be a valid PHP identifier)
     */
    public function __construct(
        public string $namespace,
        public string $enumName,
    ) {
        if ($namespace !== '') {
            self::assertNamespace($namespace);
        }
        self::assertIdentifier($enumName);
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
     * Returns the fully qualified enum name.
     *
     * Combines namespace and enum name with a backslash separator
     * (e.g., "App\Enums\Status").
     *
     * @return string The fully qualified enum name
     */
    public function fullQualifiedName(): string
    {
        if ($this->namespace === '') {
            return $this->enumName;
        }

        return $this->namespace.'\\'.$this->enumName;
    }

    /**
     * Returns the string representation of this identifier.
     *
     * @return string The fully qualified enum name
     */
    public function toString(): string
    {
        return $this->fullQualifiedName();
    }
}
