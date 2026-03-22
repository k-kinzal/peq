<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Node;

use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId\InterfaceNodeId;
use App\Analyzer\Graph\NodeKind;

/**
 * Represents an interface node in the dependency graph.
 *
 * Encapsulates information about a PHP interface including its identifier,
 * file location metadata, and well as whether it has been fully resolved during analysis.
 */
final readonly class GraphInterfaceNode implements Node
{
    /**
     * @param InterfaceNodeId $id       Unique identifier for this interface
     * @param bool            $resolved Whether this node has been fully resolved during analysis
     * @param null|FileMeta   $meta     File location metadata (null if not available)
     */
    public function __construct(
        public InterfaceNodeId $id,
        public bool $resolved = false,
        public ?FileMeta $meta = null,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function id(): InterfaceNodeId
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function kind(): NodeKind
    {
        return NodeKind::Interface;
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
