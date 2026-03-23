<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\IdentifierAssert;
use App\Analyzer\Graph\Node\ConstantNode;
use App\Analyzer\Graph\NodeId;

/**
 * Unique identifier for a constant node in the dependency graph.
 *
 * Represents a fully qualified constant identifier consisting of a namespace,
 * class name, and constant name. This ID uniquely identifies a class constant
 * within the analyzed codebase.
 *
 * @implements NodeId<ConstantNode>
 *
 * @property string $fullQualifiedName Alias for fullQualifiedName()
 */
final class ConstantNodeId implements NodeId
{
    use IdentifierAssert;

    /**
     * @param string $namespace    The namespace of the class containing the constant (must be a valid PHP namespace)
     * @param string $className    The class name containing the constant (must be a valid PHP identifier)
     * @param string $constantName The constant name (must be a valid PHP identifier)
     */
    private readonly string $stringValue;

    public function __construct(
        public readonly string $namespace,
        public readonly string $className,
        public readonly string $constantName,
    ) {
        if ($namespace !== '') {
            self::assertNamespace($namespace);
        }
        self::assertIdentifier($className);
        self::assertIdentifier($constantName);
        $prefix = $namespace === '' ? $className : $namespace.'\\'.$className;
        $this->stringValue = $prefix.'::'.$constantName;
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
     * Returns the fully qualified constant name.
     *
     * Combines namespace, class name, and constant name with appropriate separators
     * (e.g., "App\Domain\User::STATUS_ACTIVE").
     *
     * @return string The fully qualified constant name
     */
    public function fullQualifiedName(): string
    {
        return $this->stringValue;
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
