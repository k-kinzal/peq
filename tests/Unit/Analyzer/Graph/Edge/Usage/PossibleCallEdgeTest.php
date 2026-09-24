<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Edge\Usage;

use App\Analyzer\Graph\Edge\Usage\PossibleCallEdge;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Inverse\UsedByEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Usage\MethodCallEdge::class)]
#[UsesClass(\App\Analyzer\Graph\FileMeta::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\MethodNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\Node\MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[CoversClass(PossibleCallEdge::class)]
#[Small]
final class PossibleCallEdgeTest extends TestCase
{
    public function testFromRetainsItsContractAndCallSite(): void
    {
        $caller = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('Controller', 'action'));
        $contract = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('Port', 'run'));
        $meta = new \App\Analyzer\Graph\FileMeta('/source.php', 4, 1, 40);
        $call = new \App\Analyzer\Graph\Edge\Usage\MethodCallEdge($caller, $contract, $meta, 'Narrow');

        $edge = new PossibleCallEdge($call, \App\Analyzer\Graph\NodeId\MethodNodeId::of('Service', 'run'), 'Narrow');

        self::assertSame('Controller::action', $edge->from()->toString());
        self::assertSame('Service::run', $edge->to()->toString());
        self::assertSame('Port::run', $edge->call->to()->toString());
        self::assertSame('Narrow', $edge->receiverType);
        self::assertSame($meta, $edge->meta());
        self::assertSame($edge, $edge->invert()->invert());
    }

    public function testToPreservesTheRecordedRelation(): void
    {

        $caller = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('Controller', 'action'));
        $contract = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('Port', 'run'));
        $meta = new \App\Analyzer\Graph\FileMeta('/source.php', 4, 1, 40);
        $call = new \App\Analyzer\Graph\Edge\Usage\MethodCallEdge($caller, $contract, $meta);
        $edge = new PossibleCallEdge($call, \App\Analyzer\Graph\NodeId\MethodNodeId::of('Service', 'run'), 'Port');

        self::assertSame('Service::run', $edge->to()->toString());
    }

    public function testKindPreservesTheRecordedRelation(): void
    {

        $caller = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('Controller', 'action'));
        $contract = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('Port', 'run'));
        $meta = new \App\Analyzer\Graph\FileMeta('/source.php', 4, 1, 40);
        $call = new \App\Analyzer\Graph\Edge\Usage\MethodCallEdge($caller, $contract, $meta);
        $edge = new PossibleCallEdge($call, \App\Analyzer\Graph\NodeId\MethodNodeId::of('Service', 'run'), 'Port');

        self::assertSame(\App\Analyzer\Graph\EdgeKind::PossibleCall, $edge->kind());
    }

    public function testMetaPreservesTheRecordedRelation(): void
    {

        $caller = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('Controller', 'action'));
        $contract = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('Port', 'run'));
        $meta = new \App\Analyzer\Graph\FileMeta('/source.php', 4, 1, 40);
        $call = new \App\Analyzer\Graph\Edge\Usage\MethodCallEdge($caller, $contract, $meta);
        $edge = new PossibleCallEdge($call, \App\Analyzer\Graph\NodeId\MethodNodeId::of('Service', 'run'), 'Port');

        self::assertSame($meta, $edge->meta());
    }

    public function testInvertPreservesTheRecordedRelation(): void
    {

        $caller = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('Controller', 'action'));
        $contract = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('Port', 'run'));
        $meta = new \App\Analyzer\Graph\FileMeta('/source.php', 4, 1, 40);
        $call = new \App\Analyzer\Graph\Edge\Usage\MethodCallEdge($caller, $contract, $meta);
        $edge = new PossibleCallEdge($call, \App\Analyzer\Graph\NodeId\MethodNodeId::of('Service', 'run'), 'Port');

        self::assertSame($edge, $edge->invert()->invert());
    }
}
