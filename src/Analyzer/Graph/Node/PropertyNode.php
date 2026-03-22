<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Node;

use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId\PropertyNodeId;
use App\Analyzer\Graph\NodeKind;

/**
 * Represents a property node in the dependency graph.
 *
 * Encapsulates information about a PHP class property including its identifier,
 * file location metadata, and whether it has been fully resolved during analysis.
 */
final readonly class PropertyNode implements Node
{
    /**
     * @param PropertyNodeId $id       Unique identifier for this property
     * @param bool           $resolved Whether this node has been fully resolved during analysis
     * @param null|FileMeta  $meta     File location metadata (null if not available)
     */
    public function __construct(
        public PropertyNodeId $id,
        public bool $resolved = false,
        public ?FileMeta $meta = null,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function id(): PropertyNodeId
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function kind(): NodeKind
    {
        return NodeKind::Property;
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
