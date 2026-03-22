<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Node;

use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId;
use App\Analyzer\Graph\NodeId\EnumNodeId;
use App\Analyzer\Graph\NodeKind;

/**
 * Represents an enum node in the dependency graph.
 *
 * Encapsulates information about a PHP enum including its identifier,
 * file location metadata, and whether it has been fully resolved during analysis.
 */
final class EnumNode implements Node
{
    /**
     * @param EnumNodeId    $id       Unique identifier for this enum
     * @param bool          $resolved Whether this node has been fully resolved during analysis
     * @param null|FileMeta $meta     File location metadata (null if not available)
     */
    public function __construct(
        public readonly EnumNodeId $id,
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
        return NodeKind::Enum;
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
