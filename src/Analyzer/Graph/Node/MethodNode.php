<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Node;

use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeKind;

/**
 * Represents a method node in the dependency graph.
 *
 * Encapsulates information about a PHP class method including its identifier,
 * file location metadata, and whether it has been fully resolved during analysis.
 */
final class MethodNode implements Node
{
    /**
     * @param MethodNodeId  $id       Unique identifier for this method
     * @param bool          $resolved Whether this node has been fully resolved during analysis
     * @param null|FileMeta $meta     File location metadata (null if not available)
     */
    public function __construct(
        public readonly MethodNodeId $id,
        public readonly bool $resolved = false,
        public readonly ?FileMeta $meta = null,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function id(): MethodNodeId
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function kind(): NodeKind
    {
        return NodeKind::Method;
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
