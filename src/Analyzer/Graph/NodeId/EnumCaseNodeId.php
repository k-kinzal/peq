<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\Node\EnumCaseNode;
use App\Analyzer\Graph\NodeId;
use App\Analyzer\Graph\QualifiedName;

/**
 * Unique identifier for an enum case node in the dependency graph.
 *
 * Represents a fully qualified enum case identifier consisting of a namespace,
 * enum name, and case name. This ID uniquely identifies an enum case
 * within the analyzed codebase.
 *
 * @implements NodeId<ENumCaseNode>
 */
final class EnumCaseNodeId implements NodeId
{
    /**
     * The precomputed string form of this identifier.
     */
    private readonly string $stringValue;

    /**
     * @param string $namespace The namespace of the enum containing the case (must be a valid PHP namespace)
     * @param string $enumName  The enum name containing the case (must be a valid PHP identifier)
     * @param string $caseName  The case name (must be a valid PHP identifier)
     */
    public function __construct(
        public readonly string $namespace,
        public readonly string $enumName,
        public readonly string $caseName,
    ) {
        if ($namespace !== '') {
            assert(QualifiedName::isNamespace($namespace), 'The namespace must be one PHP would accept');
        }
        assert(QualifiedName::isIdentifier($enumName), 'The name must be one PHP would accept for a single symbol');
        assert(QualifiedName::isIdentifier($caseName), 'The name must be one PHP would accept for a single symbol');
        $prefix = $namespace === '' ? $enumName : $namespace.'\\'.$enumName;
        $this->stringValue = $prefix.'::'.$caseName;
    }

    /**
     * Builds the identifier from the fully qualified name of the declaring enum.
     *
     * @param string $ownerName The fully qualified enum name, as analysis reported it
     * @param string $caseName  The name of the enum case
     *
     * @return self The identifier for that enum case
     */
    public static function of(string $ownerName, string $caseName): self
    {
        $owner = new QualifiedName($ownerName);

        return new self($owner->namespace, $owner->shortName, $caseName);
    }

    /**
     * Returns the string representation of this identifier.
     *
     * @return string The fully qualified enum case name
     */
    public function toString(): string
    {
        return $this->stringValue;
    }
}
