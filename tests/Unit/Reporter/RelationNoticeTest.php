<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter;

use App\Reporter\RelationNotice;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(\App\Analyzer\Graph\EdgeIdentity::class)]
#[UsesClass(\App\Analyzer\Graph\EdgeKind::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Inverse\UsedByEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Usage\MethodCallEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Usage\PossibleCallEdge::class)]
#[UsesClass(\App\Analyzer\Graph\FileMeta::class)]
#[UsesClass(\App\Analyzer\Graph\Graph::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\MethodNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\Node\MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[CoversClass(RelationNotice::class)]
#[Small]
final class RelationNoticeTest extends TestCase
{
    public function testPossibleReverseWalkStillIdentifiesAPossibleCall(): void
    {
        $caller = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('Controller', 'action'));
        $contract = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('Port', 'run'));
        $target = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('Service', 'run'));
        $call = new \App\Analyzer\Graph\Edge\Usage\MethodCallEdge($caller, $contract, new \App\Analyzer\Graph\FileMeta('/source.php', 4, 1));
        $graph = new \App\Analyzer\Graph\Graph();
        $graph->addNodes([$caller, $contract, $target]);
        $graph->addEdge(new \App\Analyzer\Graph\Edge\Usage\PossibleCallEdge($call, $target->id(), 'Port'));

        self::assertTrue(RelationNotice::possible($graph, $caller, $target, \App\Analyzer\Graph\Direction::Uses));
        self::assertTrue(RelationNotice::possible($graph, $target, $caller, \App\Analyzer\Graph\Direction::UsedBy));
        self::assertFalse(RelationNotice::possible($graph, null, $caller, \App\Analyzer\Graph\Direction::Uses));
    }
}
