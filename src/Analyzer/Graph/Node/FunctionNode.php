<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Node;

use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Analyzer\Graph\NodeKind;

/**
 * Represents a function node in the dependency graph.
 *
 * Encapsulates information about a PHP global function including its identifier,
 * file location metadata, and whether it has been fully resolved during analysis.
 */
final class FunctionNode implements Node
{
    /**
     * @param FunctionNodeId $id       Unique identifier for this function
     * @param bool           $resolved Whether this node has been fully resolved during analysis
     * @param null|FileMeta  $meta     File location metadata (null if not available)
     */
    public function __construct(
        public readonly FunctionNodeId $id,
        public readonly bool $resolved = false,
        public readonly ?FileMeta $meta = null,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function id(): FunctionNodeId
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function kind(): NodeKind
    {
        return NodeKind::Function;
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
