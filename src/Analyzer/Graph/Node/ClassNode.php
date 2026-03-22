<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Node;

use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeKind;

/**
 * Represents a class node in the dependency graph.
 *
 * Encapsulates information about a PHP class including its identifier,
 * file location metadata, and whether it has been fully resolved during analysis.
 */
final class ClassNode implements Node
{
    /**
     * @param ClassNodeId   $id       Unique identifier for this class
     * @param bool          $resolved Whether this node has been fully resolved during analysis
     * @param null|FileMeta $meta     File location metadata (null if not available or for builtin types)
     */
    public function __construct(
        public readonly ClassNodeId $id,
        public readonly bool $resolved = false,
        public readonly ?FileMeta $meta = null,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function id(): ClassNodeId
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function kind(): NodeKind
    {
        return NodeKind::Klass;
    }

    /**
     * {@inheritdoc}
     */
    public function resolved(): bool
    {
        return $this->resolved;
    }

    /**
     * {@inheritdoc}
     */
    public function meta(): ?FileMeta
    {
        return $this->meta;
    }
}
