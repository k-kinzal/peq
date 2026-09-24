<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Edge\Usage;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Inverse\UsedByEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use Override;

/**
 * A possible dispatch target, retaining the source call and its receiver constraint.
 */
final readonly class PossibleCallEdge implements Edge
{
    /**
     * Retains the declared call alongside a possible implementation target.
     */
    public function __construct(
        public MethodCallEdge $call,
        private MethodNodeId $target,
        public string $receiverType,
        public ?string $implementationType = null,
    ) {}

    /**
     * @return NodeId<Node>
     */
    #[Override]
    public function from(): NodeId
    {
        return $this->call->from();
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function to(): MethodNodeId
    {
        return $this->target;
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function kind(): EdgeKind
    {
        return EdgeKind::PossibleCall;
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function meta(): FileMeta
    {
        return $this->call->meta();
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
