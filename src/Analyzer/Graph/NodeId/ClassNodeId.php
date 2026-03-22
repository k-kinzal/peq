<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\IdentifierAssert;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\NodeId;

/**
 * Unique identifier for a class node in the dependency graph.
 *
 * Represents a fully qualified class identifier consisting of a namespace
 * and class name. This ID uniquely identifies a PHP class within the analyzed codebase.
 *
 * @implements NodeId<ClassNode>
 *
 * @property string $fullQualifiedName Alias for fullQualifiedName()
 */
final readonly class ClassNodeId implements NodeId
{
    use IdentifierAssert;

    /**
     * @param string $namespace The namespace of the class (must be a valid PHP namespace)
     * @param string $className The class name (must be a valid PHP identifier)
     */
    public function __construct(
        public string $namespace,
        public string $className,
    ) {
        if ($namespace !== '') {
            self::assertNamespace($namespace);
        }
        self::assertIdentifier($className);
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
     * Returns the fully qualified class name.
     *
     * Combines namespace and class name with a backslash separator
     * (e.g., "App\Domain\User").
     *
     * @return string The fully qualified class name
     */
    public function fullQualifiedName(): string
    {
        return $this->namespace.'\\'.$this->className;
    }

    /**
     * Returns the string representation of this identifier.
     *
     * @return string The fully qualified class name
     */
    public function toString(): string
    {
        return $this->fullQualifiedName();
    }
}
