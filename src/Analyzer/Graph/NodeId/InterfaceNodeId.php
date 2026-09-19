<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\Node\GraphInterfaceNode;
use App\Analyzer\Graph\NodeId;
use App\Analyzer\Graph\QualifiedName;

/**
 * Unique identifier for an interface node in the dependency graph.
 *
 * Represents a fully qualified interface identifier consisting of a namespace
 * and interface name. This ID uniquely identifies a PHP interface within
 * the analyzed codebase.
 *
 * @implements NodeId<GraphInterfaceNode>
 */
final class InterfaceNodeId implements NodeId
{
    /**
     * The precomputed string form of this identifier.
     */
    private readonly string $stringValue;

    /**
     * @param string $namespace     The namespace of the interface (must be a valid PHP namespace)
     * @param string $interfaceName The interface name (must be a valid PHP identifier)
     */
    public function __construct(
        public readonly string $namespace,
        public readonly string $interfaceName,
    ) {
        if ($namespace !== '') {
            assert(QualifiedName::isNamespace($namespace), 'The namespace must be one PHP would accept');
        }
        assert(QualifiedName::isIdentifier($interfaceName), 'The name must be one PHP would accept for a single symbol');
        $this->stringValue = $namespace === '' ? $interfaceName : $namespace.'\\'.$interfaceName;
    }

    /**
     * Builds the identifier from a fully qualified name.
     *
     * @param string $fullName The fully qualified interface name, as analysis reported it
     *
     * @return self The identifier for that interface
     */
    public static function of(string $fullName): self
    {
        $name = new QualifiedName($fullName);

        return new self($name->namespace, $name->shortName);
    }

    /**
     * Returns the string representation of this identifier.
     *
     * @return string The fully qualified interface name
     */
    public function toString(): string
    {
        return $this->stringValue;
    }
}
