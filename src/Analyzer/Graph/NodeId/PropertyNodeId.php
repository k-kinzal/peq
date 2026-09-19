<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\Node\PropertyNode;
use App\Analyzer\Graph\NodeId;
use App\Analyzer\Graph\QualifiedName;

/**
 * Unique identifier for a property node in the dependency graph.
 *
 * Represents a fully qualified property identifier consisting of a namespace,
 * class name, and property name. This ID uniquely identifies a class property
 * within the analyzed codebase.
 *
 * @implements NodeId<PropertyNode>
 */
final class PropertyNodeId implements NodeId
{
    /**
     * The precomputed string form of this identifier.
     */
    private readonly string $stringValue;

    /**
     * @param string $namespace    The namespace of the class containing the property (must be a valid PHP namespace)
     * @param string $className    The class name containing the property (must be a valid PHP identifier)
     * @param string $propertyName The property name (must be a valid PHP identifier)
     */
    public function __construct(
        public readonly string $namespace,
        public readonly string $className,
        public readonly string $propertyName,
    ) {
        if ($namespace !== '') {
            assert(QualifiedName::isNamespace($namespace), 'The namespace must be one PHP would accept');
        }
        assert(QualifiedName::isIdentifier($className), 'The name must be one PHP would accept for a single symbol');
        assert(QualifiedName::isIdentifier($propertyName), 'The name must be one PHP would accept for a single symbol');
        $prefix = $namespace === '' ? $className : $namespace.'\\'.$className;
        $this->stringValue = $prefix.'::'.$propertyName;
    }

    /**
     * Builds the identifier from the fully qualified name of the declaring class.
     *
     * @param string $ownerName    The fully qualified class name, as analysis reported it
     * @param string $propertyName The name of the property
     *
     * @return self The identifier for that property
     */
    public static function of(string $ownerName, string $propertyName): self
    {
        $owner = new QualifiedName($ownerName);

        return new self($owner->namespace, $owner->shortName, $propertyName);
    }

    /**
     * Returns the string representation of this identifier.
     *
     * @return string The fully qualified property name
     */
    public function toString(): string
    {
        return $this->stringValue;
    }
}
