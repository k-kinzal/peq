<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\DebugAnalyzer\Generator;

use App\Analyzer\DebugAnalyzer\Generator\GeneratedGraph;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Declaration\MethodEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[UsesClass(\App\Analyzer\Graph\EdgeIdentity::class)]
#[CoversClass(GeneratedGraph::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(MethodEdge::class)]
#[UsesClass(Edge\Inverse\DeclaredInEdge::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(\App\Analyzer\Graph\Graph::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(ClassNode::class)]
#[UsesClass(MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class GeneratedGraphTest extends TestCase
{
    public function testRootedAtHoldsTheNodeItWasGrownFrom(): void
    {
        $node = new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true);

        self::assertSame($node, GeneratedGraph::rootedAt($node)->root);
    }

    public function testRootedAtRecordsThatNodeInItsGraph(): void
    {
        self::assertNotNull(GeneratedGraph::rootedAt(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true))->graph->nodeNamed('App\Domain\Invoice'));
    }

    public function testRootedAtHoldsNothingElse(): void
    {
        self::assertCount(1, GeneratedGraph::rootedAt(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true))->graph->nodes());
    }

    public function testRelatedToRecordsTheRelationBetweenTheTwoRoots(): void
    {
        $result = GeneratedGraph::rootedAt(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true))->relatedTo(
            GeneratedGraph::rootedAt(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true)),
            static fn (ClassNode $owner, MethodNode $method): Edge => new MethodEdge($owner, $method, new FileMeta('/project/src/Domain/Invoice.php', 12, 1)),
        );

        self::assertCount(1, $result->graph->forwardEdges());
    }

    public function testRelatedToKeepsTheRootItStartedFrom(): void
    {
        $root = new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true);
        $result = GeneratedGraph::rootedAt($root)->relatedTo(
            GeneratedGraph::rootedAt(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true)),
            static fn (ClassNode $owner, MethodNode $method): Edge => new MethodEdge($owner, $method, new FileMeta('/project/src/Domain/Invoice.php', 12, 1)),
        );

        self::assertSame($root, $result->root);
    }

    public function testRelatedToHoldsTheSymbolsOfBothGraphs(): void
    {
        $result = GeneratedGraph::rootedAt(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true))->relatedTo(
            GeneratedGraph::rootedAt(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true)),
            static fn (ClassNode $owner, MethodNode $method): Edge => new MethodEdge($owner, $method, new FileMeta('/project/src/Domain/Invoice.php', 12, 1)),
        );

        self::assertNotNull($result->graph->nodeNamed('App\Domain\Invoice'));
        self::assertNotNull($result->graph->nodeNamed('App\Domain\Invoice::total'));
    }

    public function testRelatedToLeavesTheGraphItStartedFromUntouched(): void
    {
        $start = GeneratedGraph::rootedAt(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true));
        $start->relatedTo(
            GeneratedGraph::rootedAt(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true)),
            static fn (ClassNode $owner, MethodNode $method): Edge => new MethodEdge($owner, $method, new FileMeta('/project/src/Domain/Invoice.php', 12, 1)),
        );

        self::assertCount(1, $start->graph->nodes());
    }

    public function testRelatedToGivesBothRootsToTheRelationWithTheirOwnTypes(): void
    {
        $result = GeneratedGraph::rootedAt(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true))->relatedTo(
            GeneratedGraph::rootedAt(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true)),
            static fn (ClassNode $owner, MethodNode $method): Edge => new MethodEdge($owner, $method, new FileMeta('/project/src/Domain/Invoice.php', 12, 1)),
        );

        self::assertSame(
            'App\Domain\Invoice -> App\Domain\Invoice::total',
            $result->graph->forwardEdges()[0]->from()->toString().' -> '.$result->graph->forwardEdges()[0]->to()->toString(),
        );
    }
}
