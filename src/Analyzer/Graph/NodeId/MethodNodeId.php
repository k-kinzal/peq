<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\IdentifierAssert;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId;

/**
 * Unique identifier for a method node in the dependency graph.
 *
 * Represents a fully qualified method identifier consisting of a namespace,
 * class name, and method name. This ID uniquely identifies a class method
 * within the analyzed codebase.
 *
 * @implements NodeId<MethodNode>
 *
 * @property string $fullQualifiedName Alias for fullQualifiedName()
 */
final readonly class MethodNodeId implements NodeId
{
    use IdentifierAssert;

    /**
     * @param string $namespace  The namespace of the class containing the method (must be a valid PHP namespace)
     * @param string $className  The class name containing the method (must be a valid PHP identifier)
     * @param string $methodName The method name (must be a valid PHP identifier)
     */
    public function __construct(
        public string $namespace,
        public string $className,
        public string $methodName,
    ) {
        if ($namespace !== '') {
            self::assertNamespace($namespace);
        }
        self::assertIdentifier($className);
        self::assertIdentifier($methodName);
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
     * Returns the fully qualified method name.
     *
     * Combines namespace, class name, and method name with appropriate separators
     * (e.g., "App\Domain\User::getName").
     *
     * @return string The fully qualified method name
     */
    public function fullQualifiedName(): string
    {
        return $this->namespace.'\\'.$this->className.'::'.$this->methodName;
    }

    /**
     * Returns the string representation of this identifier.
     *
     * @return string The fully qualified method name
     */
    public function toString(): string
    {
        return $this->fullQualifiedName();
    }
}
