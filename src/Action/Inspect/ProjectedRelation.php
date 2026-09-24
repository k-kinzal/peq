<?php

declare(strict_types=1);

namespace App\Action\Inspect;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Inverse\UsedByEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId;
use Override;

/**
 * A report relation between owners, retaining the kind and location of its evidence.
 */
final readonly class ProjectedRelation implements Edge
{
    /**
     * Projects a relation onto its owning symbols for an inspection.
     */
    public function __construct(private Node $origin, private Node $target, public Edge $evidence) {}

    /**
     * @return NodeId<Node>
     */
    #[Override]
    public function from(): NodeId
    {
        return $this->origin->id();
    }

    /**
     * @return NodeId<Node>
     */
    #[Override]
    public function to(): NodeId
    {
        return $this->target->id();
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function kind(): EdgeKind
    {
        return $this->evidence->kind();
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function meta(): FileMeta
    {
        return $this->evidence->meta();
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function invert(): Edge
    {
        return new UsedByEdge($this);
    }
}
