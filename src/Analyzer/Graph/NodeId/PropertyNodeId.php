<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\IdentifierAssert;
use App\Analyzer\Graph\Node\PropertyNode;
use App\Analyzer\Graph\NodeId;

/**
 * Unique identifier for a property node in the dependency graph.
 *
 * Represents a fully qualified property identifier consisting of a namespace,
 * class name, and property name. This ID uniquely identifies a class property
 * within the analyzed codebase.
 *
 * @implements NodeId<PropertyNode>
 *
 * @property string $fullQualifiedName Alias for fullQualifiedName()
 */
final readonly class PropertyNodeId implements NodeId
{
    use IdentifierAssert;

    /**
     * @param string $namespace    The namespace of the class containing the property (must be a valid PHP namespace)
     * @param string $className    The class name containing the property (must be a valid PHP identifier)
     * @param string $propertyName The property name (must be a valid PHP identifier)
     */
    public function __construct(
        public string $namespace,
        public string $className,
        public string $propertyName,
    ) {
        if ($namespace !== '') {
            self::assertNamespace($namespace);
        }
        self::assertIdentifier($className);
        self::assertIdentifier($propertyName);
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
     * Returns the fully qualified property name.
     *
     * Combines namespace, class name, and property name with appropriate separators
     * (e.g., "App\Domain\User::username").
     *
     * @return string The fully qualified property name
     */
    public function fullQualifiedName(): string
    {
        return $this->namespace.'\\'.$this->className.'::'.$this->propertyName;
    }

    /**
     * Returns the string representation of this identifier.
     *
     * @return string The fully qualified property name
     */
    public function toString(): string
    {
        return $this->fullQualifiedName();
    }
}
