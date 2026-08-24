<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\NodeId;
use App\Analyzer\Graph\QualifiedName;

/**
 * Unique identifier for a class node in the dependency graph.
 *
 * Represents a fully qualified class identifier consisting of a namespace
 * and class name. This ID uniquely identifies a PHP class within the analyzed codebase.
 *
 * @implements NodeId<ClassNode>
 */
final class ClassNodeId implements NodeId
{
    /**
     * The precomputed string form of this identifier.
     */
    private readonly string $stringValue;

    /**
     * @param string $namespace The namespace of the class (must be a valid PHP namespace)
     * @param string $className The class name (must be a valid PHP identifier)
     */
    public function __construct(
        public readonly string $namespace,
        public readonly string $className,
    ) {
        if ($namespace !== '') {
            assert(QualifiedName::isNamespace($namespace), 'The namespace must be one PHP would accept');
        }
        assert(QualifiedName::isIdentifier($className), 'The name must be one PHP would accept for a single symbol');
        $this->stringValue = $namespace === '' ? $className : $namespace.'\\'.$className;
    }

    /**
     * Builds the identifier from a fully qualified name.
     *
     * @param string $fullName The fully qualified class name, as analysis reported it
     *
     * @example Building an identifier from a written name
     *     \App\Analyzer\Graph\NodeId\ClassNodeId::of('App\\Domain\\Invoice')->toString() // => 'App\\Domain\\Invoice'
     * @example A global class has no namespace
     *     \App\Analyzer\Graph\NodeId\ClassNodeId::of('Invoice')->namespace // => ''
     *
     * @return self The identifier for that class
     */
    public static function of(string $fullName): self
    {
        $name = new QualifiedName($fullName);

        return new self($name->namespace, $name->shortName);
    }

    /**
     * Returns the string representation of this identifier.
     *
     * @return string The fully qualified class name
     */
    public function toString(): string
    {
        return $this->stringValue;
    }
}
