<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\IdentifierAssert;
use App\Analyzer\Graph\Node\EnumCaseNode;
use App\Analyzer\Graph\NodeId;

/**
 * Unique identifier for an enum case node in the dependency graph.
 *
 * Represents a fully qualified enum case identifier consisting of a namespace,
 * enum name, and case name. This ID uniquely identifies an enum case
 * within the analyzed codebase.
 *
 * @implements NodeId<ENumCaseNode>
 *
 * @property string $fullQualifiedName Alias for fullQualifiedName()
 */
final readonly class EnumCaseNodeId implements NodeId
{
    use IdentifierAssert;

    /**
     * @param string $namespace The namespace of the enum containing the case (must be a valid PHP namespace)
     * @param string $enumName  The enum name containing the case (must be a valid PHP identifier)
     * @param string $caseName  The case name (must be a valid PHP identifier)
     */
    public function __construct(
        public string $namespace,
        public string $enumName,
        public string $caseName,
    ) {
        if ($namespace !== '') {
            self::assertNamespace($namespace);
        }
        self::assertIdentifier($enumName);
        self::assertIdentifier($caseName);
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
     * Returns the fully qualified enum case name.
     *
     * Combines namespace, enum name, and case name with appropriate separators
     * (e.g., "App\Enums\Status::Pending").
     *
     * @return string The fully qualified enum case name
     */
    public function fullQualifiedName(): string
    {
        if ($this->namespace === '') {
            return $this->enumName.'::'.$this->caseName;
        }

        return $this->namespace.'\\'.$this->enumName.'::'.$this->caseName;
    }

    /**
     * Returns the string representation of this identifier.
     *
     * @return string The fully qualified enum case name
     */
    public function toString(): string
    {
        return $this->fullQualifiedName();
    }
}
