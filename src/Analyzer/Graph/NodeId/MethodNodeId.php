<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId;
use App\Analyzer\Graph\QualifiedName;

/**
 * Unique identifier for a method node in the dependency graph.
 *
 * Represents a fully qualified method identifier consisting of a namespace,
 * class name, and method name. This ID uniquely identifies a class method
 * within the analyzed codebase.
 *
 * @implements NodeId<MethodNode>
 */
final class MethodNodeId implements NodeId
{
    /**
     * The precomputed string form of this identifier.
     */
    private readonly string $stringValue;

    /**
     * @param string $namespace  The namespace of the class containing the method (must be a valid PHP namespace)
     * @param string $className  The class name containing the method (must be a valid PHP identifier)
     * @param string $methodName The method name (must be a valid PHP identifier)
     */
    public function __construct(
        public readonly string $namespace,
        public readonly string $className,
        public readonly string $methodName,
    ) {
        if ($namespace !== '') {
            assert(QualifiedName::isNamespace($namespace), 'The namespace must be one PHP would accept');
        }
        assert(QualifiedName::isIdentifier($className), 'The name must be one PHP would accept for a single symbol');
        assert(QualifiedName::isIdentifier($methodName), 'The name must be one PHP would accept for a single symbol');
        $prefix = $namespace === '' ? $className : $namespace.'\\'.$className;
        $this->stringValue = $prefix.'::'.$methodName;
    }

    /**
     * Builds the identifier from the fully qualified name of the declaring class.
     *
     * @param string $ownerName  The fully qualified class name, as analysis reported it
     * @param string $methodName The name of the method
     *
     * @example A method is named apart from the class that declares it
     *     \App\Analyzer\Graph\NodeId\MethodNodeId::of('App\\Domain\\Invoice', 'total')->toString() // => 'App\\Domain\\Invoice::total'
     *
     * @return self The identifier for that method
     */
    public static function of(string $ownerName, string $methodName): self
    {
        $owner = new QualifiedName($ownerName);

        return new self($owner->namespace, $owner->shortName, $methodName);
    }

    /**
     * Returns the string representation of this identifier.
     *
     * @return string The fully qualified method name
     */
    public function toString(): string
    {
        return $this->stringValue;
    }
}
