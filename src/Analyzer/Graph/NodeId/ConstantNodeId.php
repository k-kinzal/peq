<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\Node\ConstantNode;
use App\Analyzer\Graph\NodeId;
use App\Analyzer\Graph\QualifiedName;

/**
 * Unique identifier for a constant node in the dependency graph.
 *
 * Represents a fully qualified constant identifier consisting of a namespace,
 * class name, and constant name. This ID uniquely identifies a class constant
 * within the analyzed codebase.
 *
 * @implements NodeId<ConstantNode>
 */
final class ConstantNodeId implements NodeId
{
    /**
     * The precomputed string form of this identifier.
     */
    private readonly string $stringValue;

    /**
     * @param string $namespace    The namespace of the class containing the constant (must be a valid PHP namespace)
     * @param string $className    The class name containing the constant (must be a valid PHP identifier)
     * @param string $constantName The constant name (must be a valid PHP identifier)
     */
    public function __construct(
        public readonly string $namespace,
        public readonly string $className,
        public readonly string $constantName,
    ) {
        if ($namespace !== '') {
            assert(QualifiedName::isNamespace($namespace), 'The namespace must be one PHP would accept');
        }
        assert(QualifiedName::isIdentifier($className), 'The name must be one PHP would accept for a single symbol');
        assert(QualifiedName::isIdentifier($constantName), 'The name must be one PHP would accept for a single symbol');
        $prefix = $namespace === '' ? $className : $namespace.'\\'.$className;
        $this->stringValue = $prefix.'::'.$constantName;
    }

    /**
     * Builds the identifier from the fully qualified name of the declaring class.
     *
     * @param string $ownerName    The fully qualified class name, as analysis reported it
     * @param string $constantName The name of the constant
     *
     * @return self The identifier for that constant
     */
    public static function of(string $ownerName, string $constantName): self
    {
        $owner = new QualifiedName($ownerName);

        return new self($owner->namespace, $owner->shortName, $constantName);
    }

    /**
     * Returns the string representation of this identifier.
     *
     * @return string The fully qualified constant name
     */
    public function toString(): string
    {
        return $this->stringValue;
    }
}
