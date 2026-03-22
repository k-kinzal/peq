<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Node;

use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId;
use App\Analyzer\Graph\NodeId\TraitNodeId;
use App\Analyzer\Graph\NodeKind;

/**
 * Represents a trait node in the dependency graph.
 *
 * Encapsulates information about a PHP trait including its identifier,
 * file location metadata, and whether it has been fully resolved during analysis.
 */
final class TraitNode implements Node
{
    /**
     * @param TraitNodeId   $id       Unique identifier for this trait
     * @param bool          $resolved Whether this node has been fully resolved during analysis
     * @param null|FileMeta $meta     File location metadata (null if not available)
     */
    public function __construct(
        public readonly TraitNodeId $id,
        public readonly bool $resolved = false,
        public readonly ?FileMeta $meta = null,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function id(): NodeId
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function kind(): NodeKind
    {
        return NodeKind::Trait;
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
