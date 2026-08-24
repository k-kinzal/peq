<?php

declare(strict_types=1);

namespace Tests\Fixture\Graph;

use App\Analyzer\Graph\AuthoredEdge;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\UsedByEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;

/**
 * An authored edge of a chosen kind between two arbitrary nodes.
 *
 * The real edge types constrain which node types they may join, which is what makes
 * them safe and what makes them unsuitable for testing the graph's own bookkeeping.
 * This double joins any two nodes so that a test can be about adjacency, duplication
 * or inversion rather than about which relation happens to be expressible.
 */
final class StubEdge extends AuthoredEdge
{
    /**
     * @param Node     $from The node the relation starts at
     * @param Node     $to   The node the relation points at
     * @param FileMeta $meta Where the relation is written
     * @param EdgeKind $kind The kind the edge reports
     */
    public function __construct(
        Node $from,
        Node $to,
        FileMeta $meta,
        private readonly EdgeKind $kind = EdgeKind::MethodCall,
    ) {
        parent::__construct($from, $to, $meta);
    }

    /**
     * {@inheritdoc}
     */
    public function kind(): EdgeKind
    {
        return $this->kind;
    }

    /**
     * {@inheritdoc}
     */
    public function invert(): Edge
    {
        return new UsedByEdge($this);
    }
}
