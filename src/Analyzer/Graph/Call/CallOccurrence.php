<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Call;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Inverse\UsedByEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId;
use Override;

/**
 * One resolved or possible target of a call site, retaining its original evidence.
 */
readonly class CallOccurrence implements Edge
{
    /**
     * Attaches source syntax and lexical ownership to a relation resolved by an engine.
     */
    public function __construct(public Edge $relation, public CallSite $site) {}

    /**
     * @return NodeId<Node>
     */
    #[Override]
    public function from(): NodeId
    {
        return $this->site->caller->id();
    }

    /**
     * @return NodeId<Node>
     */
    #[Override]
    public function to(): NodeId
    {
        return $this->relation->to();
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function kind(): EdgeKind
    {
        return $this->relation->kind();
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function meta(): FileMeta
    {
        return $this->site->meta;
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
