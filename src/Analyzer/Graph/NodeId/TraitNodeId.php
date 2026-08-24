<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\Node\TraitNode;
use App\Analyzer\Graph\NodeId;
use App\Analyzer\Graph\QualifiedName;

/**
 * Unique identifier for a trait node in the dependency graph.
 *
 * Represents a fully qualified trait identifier consisting of a namespace
 * and trait name. This ID uniquely identifies a PHP trait within
 * the analyzed codebase.
 *
 * @implements NodeId<TraitNode>
 */
final class TraitNodeId implements NodeId
{
    /**
     * The precomputed string form of this identifier.
     */
    private readonly string $stringValue;

    /**
     * @param string $namespace The namespace of the trait (must be a valid PHP namespace)
     * @param string $traitName The trait name (must be a valid PHP identifier)
     */
    public function __construct(
        public readonly string $namespace,
        public readonly string $traitName,
    ) {
        if ($namespace !== '') {
            assert(QualifiedName::isNamespace($namespace), 'The namespace must be one PHP would accept');
        }
        assert(QualifiedName::isIdentifier($traitName), 'The name must be one PHP would accept for a single symbol');
        $this->stringValue = $namespace === '' ? $traitName : $namespace.'\\'.$traitName;
    }

    /**
     * Builds the identifier from a fully qualified name.
     *
     * @param string $fullName The fully qualified trait name, as analysis reported it
     *
     * @return self The identifier for that trait
     */
    public static function of(string $fullName): self
    {
        $name = new QualifiedName($fullName);

        return new self($name->namespace, $name->shortName);
    }

    /**
     * Returns the string representation of this identifier.
     *
     * @return string The fully qualified trait name
     */
    public function toString(): string
    {
        return $this->stringValue;
    }
}
