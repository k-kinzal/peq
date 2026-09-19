<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\NodeId;
use App\Analyzer\Graph\QualifiedName;

/**
 * Unique identifier for a function node in the dependency graph.
 *
 * Represents a fully qualified function identifier consisting of a namespace
 * and function name. This ID uniquely identifies a global function within
 * the analyzed codebase.
 *
 * @implements NodeId<FunctionNode>
 */
final class FunctionNodeId implements NodeId
{
    /**
     * The precomputed string form of this identifier.
     */
    private readonly string $stringValue;

    /**
     * @param string $namespace    The namespace of the function (must be a valid PHP namespace)
     * @param string $functionName The function name (must be a valid PHP identifier)
     */
    public function __construct(
        public readonly string $namespace,
        public readonly string $functionName,
    ) {
        if ($namespace !== '') {
            assert(QualifiedName::isNamespace($namespace), 'The namespace must be one PHP would accept');
        }
        assert(QualifiedName::isIdentifier($functionName), 'The name must be one PHP would accept for a single symbol');
        $this->stringValue = $namespace === '' ? $functionName : $namespace.'\\'.$functionName;
    }

    /**
     * Builds the identifier from a fully qualified name.
     *
     * @param string $fullName The fully qualified function name, as analysis reported it
     *
     * @return self The identifier for that function
     */
    public static function of(string $fullName): self
    {
        $name = new QualifiedName($fullName);

        return new self($name->namespace, $name->shortName);
    }

    /**
     * Returns the string representation of this identifier.
     *
     * @return string The fully qualified function name
     */
    public function toString(): string
    {
        return $this->stringValue;
    }
}
