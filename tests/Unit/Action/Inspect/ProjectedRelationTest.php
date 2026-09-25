<?php

declare(strict_types=1);

namespace Tests\Unit\Action\Inspect;

use App\Action\Inspect\ProjectedRelation;
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
#[UsesClass(\App\Analyzer\Graph\NodeId\ClassNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\MethodNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\Node\ClassNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[CoversClass(ProjectedRelation::class)]
#[UsesClass(\App\Analyzer\Graph\EdgeIdentity::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Usage\StaticCallEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Graph::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\UnknownNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\Node\UnknownNode::class)]
#[Small]
final class ProjectedRelationTest extends TestCase
{
    public function testFromPreservesTheRelationAndItsSourceLocation(): void
    {
        $from = new \App\Analyzer\Graph\Node\ClassNode(\App\Analyzer\Graph\NodeId\ClassNodeId::of('Controller'));
        $to = new \App\Analyzer\Graph\Node\ClassNode(\App\Analyzer\Graph\NodeId\ClassNodeId::of('Service'));
        $call = new \App\Analyzer\Graph\Edge\Usage\MethodCallEdge(
            new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('Controller', 'action')),
            new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('Service', 'run')),
            new \App\Analyzer\Graph\FileMeta('/source.php', 4, 1),
        );

        $projected = new ProjectedRelation($from, $to, $call);

        self::assertSame('Controller', $projected->from()->toString());
        self::assertSame('Service', $projected->to()->toString());
        self::assertSame(\App\Analyzer\Graph\EdgeKind::MethodCall, $projected->kind());
        self::assertSame($call->meta(), $projected->meta());
        self::assertSame($projected, $projected->invert()->invert());
    }

    public function testToPreservesTheRecordedRelation(): void
    {

        $caller = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('Controller', 'action'));
        $contract = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('Port', 'run'));
        $meta = new \App\Analyzer\Graph\FileMeta('/source.php', 4, 1, 40);
        $call = new \App\Analyzer\Graph\Edge\Usage\MethodCallEdge($caller, $contract, $meta);
        $edge = new ProjectedRelation(new \App\Analyzer\Graph\Node\ClassNode(\App\Analyzer\Graph\NodeId\ClassNodeId::of('Controller')), new \App\Analyzer\Graph\Node\ClassNode(\App\Analyzer\Graph\NodeId\ClassNodeId::of('Port')), $call);

        self::assertSame('Port', $edge->to()->toString());
    }

    public function testKindPreservesTheRecordedRelation(): void
    {

        $caller = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('Controller', 'action'));
        $contract = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('Port', 'run'));
        $meta = new \App\Analyzer\Graph\FileMeta('/source.php', 4, 1, 40);
        $call = new \App\Analyzer\Graph\Edge\Usage\MethodCallEdge($caller, $contract, $meta);
        $edge = new ProjectedRelation(new \App\Analyzer\Graph\Node\ClassNode(\App\Analyzer\Graph\NodeId\ClassNodeId::of('Controller')), new \App\Analyzer\Graph\Node\ClassNode(\App\Analyzer\Graph\NodeId\ClassNodeId::of('Port')), $call);

        self::assertSame(\App\Analyzer\Graph\EdgeKind::MethodCall, $edge->kind());
    }

    public function testMetaPreservesTheRecordedRelation(): void
    {

        $caller = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('Controller', 'action'));
        $contract = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('Port', 'run'));
        $meta = new \App\Analyzer\Graph\FileMeta('/source.php', 4, 1, 40);
        $call = new \App\Analyzer\Graph\Edge\Usage\MethodCallEdge($caller, $contract, $meta);
        $edge = new ProjectedRelation(new \App\Analyzer\Graph\Node\ClassNode(\App\Analyzer\Graph\NodeId\ClassNodeId::of('Controller')), new \App\Analyzer\Graph\Node\ClassNode(\App\Analyzer\Graph\NodeId\ClassNodeId::of('Port')), $call);

        self::assertSame($meta, $edge->meta());
    }

    public function testInvertPreservesTheRecordedRelation(): void
    {

        $caller = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('Controller', 'action'));
        $contract = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('Port', 'run'));
        $meta = new \App\Analyzer\Graph\FileMeta('/source.php', 4, 1, 40);
        $call = new \App\Analyzer\Graph\Edge\Usage\MethodCallEdge($caller, $contract, $meta);
        $edge = new ProjectedRelation(new \App\Analyzer\Graph\Node\ClassNode(\App\Analyzer\Graph\NodeId\ClassNodeId::of('Controller')), new \App\Analyzer\Graph\Node\ClassNode(\App\Analyzer\Graph\NodeId\ClassNodeId::of('Port')), $call);

        self::assertSame($edge, $edge->invert()->invert());
    }

    public function testAddToCollapsesOccurrencesButRetainsKindsAndTargets(): void
    {
        $from = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('A', 'run'));
        $to = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('B', 'run'));
        $other = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('C', 'run'));
        $graph = new \App\Analyzer\Graph\Graph();
        $first = new ProjectedRelation($from, $to, new \App\Analyzer\Graph\Edge\Usage\MethodCallEdge($from, $to, new \App\Analyzer\Graph\FileMeta('/a.php', 1, 1)));
        $first->addTo($graph);
        (new ProjectedRelation($from, $to, new \App\Analyzer\Graph\Edge\Usage\MethodCallEdge($from, $to, new \App\Analyzer\Graph\FileMeta('/a.php', 2, 1))))->addTo($graph);
        $differentKind = new ProjectedRelation($from, $to, new \App\Analyzer\Graph\Edge\Usage\StaticCallEdge($from, $to, new \App\Analyzer\Graph\FileMeta('/a.php', 3, 1)));
        $differentKind->addTo($graph);
        $differentTarget = new ProjectedRelation($from, $other, new \App\Analyzer\Graph\Edge\Usage\MethodCallEdge($from, $other, new \App\Analyzer\Graph\FileMeta('/a.php', 4, 1)));
        $differentTarget->addTo($graph);

        self::assertSame([$first, $differentKind, $differentTarget], $graph->forwardEdges());
    }
}
