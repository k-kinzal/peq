<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\Node\BuiltinNode;
use App\Analyzer\Graph\NodeId;
use App\Analyzer\Graph\QualifiedName;
use Override;

/**
 * Unique identifier for a builtin type node in the dependency graph.
 *
 * Represents a fully qualified identifier for PHP builtin types (e.g., string, int, array).
 * This ID uniquely identifies a PHP builtin type within the analyzed codebase.
 *
 * @implements NodeId<BuiltinNode>
 */
final readonly class BuiltinNodeId implements NodeId
{
    /**
     * The precomputed string form of this identifier.
     */
    private string $stringValue;

    /**
     * @param string $namespace The namespace of the builtin type (must be a valid PHP namespace)
     * @param string $name      The builtin type name (must be a valid PHP identifier)
     */
    public function __construct(
        public string $namespace,
        public string $name,
    ) {
        if ($namespace !== '') {
            assert(QualifiedName::isNamespace($namespace), 'The namespace must be one PHP would accept');
        }
        assert(QualifiedName::isIdentifier($name), 'The name must be one PHP would accept for a single symbol');
        $this->stringValue = $namespace === '' ? $name : $namespace.'\\'.$name;
    }

    /**
     * Builds the identifier from a fully qualified name.
     *
     * @param string $fullName The fully qualified builtin type name, as analysis reported it
     *
     * @return self The identifier for that builtin type
     */
    public static function of(string $fullName): self
    {
        $name = new QualifiedName($fullName);

        return new self($name->namespace, $name->shortName);
    }

    /**
     * Returns the string representation of this identifier.
     *
     * @return string The fully qualified builtin type name
     */
    #[Override]
    public function toString(): string
    {
        return $this->stringValue;
    }
}
