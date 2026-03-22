<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Node;

use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId\UnknownNodeId;
use App\Analyzer\Graph\NodeKind;

/**
 * Represents an unknown node in the dependency graph.
 *
 * Encapsulates information about nodes that could not be resolved or classified
 * during analysis. These are typically used as placeholders for unresolved
 * dependencies or external references not present in the analyzed codebase.
 */
final class UnknownNode implements Node
{
    /**
     * @param UnknownNodeId $id       Unique identifier for this unknown node
     * @param bool          $resolved Whether this node has been fully resolved during analysis (typically false)
     * @param null|FileMeta $meta     File location metadata (typically null for unknown nodes)
     */
    public function __construct(
        public readonly UnknownNodeId $id,
        public readonly bool $resolved = false,
        public readonly ?FileMeta $meta = null,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function id(): UnknownNodeId
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function kind(): NodeKind
    {
        return NodeKind::Unknown;
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
