<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Node;

use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId\ConstantNodeId;
use App\Analyzer\Graph\NodeKind;

/**
 * Represents a constant node in the dependency graph.
 *
 * Encapsulates information about a PHP class constant including its identifier,
 * file location metadata, and whether it has been fully resolved during analysis.
 */
final class ConstantNode implements Node
{
    /**
     * @param ConstantNodeId $id       Unique identifier for this constant
     * @param bool           $resolved Whether this node has been fully resolved during analysis
     * @param null|FileMeta  $meta     File location metadata (null if not available)
     */
    public function __construct(
        public readonly ConstantNodeId $id,
        public readonly bool $resolved = false,
        public readonly ?FileMeta $meta = null,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function id(): ConstantNodeId
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function kind(): NodeKind
    {
        return NodeKind::Constant;
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
