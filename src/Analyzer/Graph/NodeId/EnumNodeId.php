<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\Node\EnumNode;
use App\Analyzer\Graph\NodeId;
use App\Analyzer\Graph\QualifiedName;

/**
 * Unique identifier for an enum node in the dependency graph.
 *
 * Represents a fully qualified enum identifier consisting of a namespace
 * and enum name. This ID uniquely identifies a PHP enum within
 * the analyzed codebase.
 *
 * @implements NodeId<EnumNode>
 */
final class EnumNodeId implements NodeId
{
    /**
     * The precomputed string form of this identifier.
     */
    private readonly string $stringValue;

    /**
     * @param string $namespace The namespace of the enum (must be a valid PHP namespace)
     * @param string $enumName  The enum name (must be a valid PHP identifier)
     */
    public function __construct(
        public readonly string $namespace,
        public readonly string $enumName,
    ) {
        if ($namespace !== '') {
            assert(QualifiedName::isNamespace($namespace), 'The namespace must be one PHP would accept');
        }
        assert(QualifiedName::isIdentifier($enumName), 'The name must be one PHP would accept for a single symbol');
        $this->stringValue = $namespace === '' ? $enumName : $namespace.'\\'.$enumName;
    }

    /**
     * Builds the identifier from a fully qualified name.
     *
     * @param string $fullName The fully qualified enum name, as analysis reported it
     *
     * @return self The identifier for that enum
     */
    public static function of(string $fullName): self
    {
        $name = new QualifiedName($fullName);

        return new self($name->namespace, $name->shortName);
    }

    /**
     * Returns the string representation of this identifier.
     *
     * @return string The fully qualified enum name
     */
    public function toString(): string
    {
        return $this->stringValue;
    }
}
