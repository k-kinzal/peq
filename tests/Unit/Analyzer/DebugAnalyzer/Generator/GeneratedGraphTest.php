<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\DebugAnalyzer\Generator;

use App\Analyzer\DebugAnalyzer\Generator\GeneratedGraph;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Declaration\MethodEdge;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Graph\SampleEdges;
use Tests\Fixture\Graph\SampleNodes;

/**
 * @internal
 */
#[CoversClass(GeneratedGraph::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(MethodEdge::class)]
#[UsesClass(Edge\Inverse\DeclaredInEdge::class)]
#[UsesClass(\App\Analyzer\Graph\FileMeta::class)]
#[UsesClass(\App\Analyzer\Graph\Graph::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\ClassNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\MethodNodeId::class)]
#[UsesClass(ClassNode::class)]
#[UsesClass(MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class GeneratedGraphTest extends TestCase
{
    public function testRootedAtHoldsTheNodeItWasGrownFrom(): void
    {
        $node = SampleNodes::invoice();

        self::assertSame($node, GeneratedGraph::rootedAt($node)->root);
    }

    public function testRootedAtRecordsThatNodeInItsGraph(): void
    {
        self::assertNotNull(GeneratedGraph::rootedAt(SampleNodes::invoice())->graph->nodeNamed('App\Domain\Invoice'));
    }

    public function testRootedAtHoldsNothingElse(): void
    {
        self::assertCount(1, GeneratedGraph::rootedAt(SampleNodes::invoice())->graph->nodes());
    }

    public function testRelatedToRecordsTheRelationBetweenTheTwoRoots(): void
    {
        $result = GeneratedGraph::rootedAt(SampleNodes::invoice())->relatedTo(
            GeneratedGraph::rootedAt(SampleNodes::total()),
            static fn (ClassNode $owner, MethodNode $method): Edge => new MethodEdge($owner, $method, SampleEdges::meta()),
        );

        self::assertCount(1, $result->graph->authoredEdges());
    }

    public function testRelatedToKeepsTheRootItStartedFrom(): void
    {
        $root = SampleNodes::invoice();
        $result = GeneratedGraph::rootedAt($root)->relatedTo(
            GeneratedGraph::rootedAt(SampleNodes::total()),
            static fn (ClassNode $owner, MethodNode $method): Edge => new MethodEdge($owner, $method, SampleEdges::meta()),
        );

        self::assertSame($root, $result->root);
    }

    public function testRelatedToHoldsTheSymbolsOfBothGraphs(): void
    {
        $result = GeneratedGraph::rootedAt(SampleNodes::invoice())->relatedTo(
            GeneratedGraph::rootedAt(SampleNodes::total()),
            static fn (ClassNode $owner, MethodNode $method): Edge => new MethodEdge($owner, $method, SampleEdges::meta()),
        );

        self::assertNotNull($result->graph->nodeNamed('App\Domain\Invoice'));
        self::assertNotNull($result->graph->nodeNamed('App\Domain\Invoice::total'));
    }

    public function testRelatedToLeavesTheGraphItStartedFromUntouched(): void
    {
        $start = GeneratedGraph::rootedAt(SampleNodes::invoice());
        $start->relatedTo(
            GeneratedGraph::rootedAt(SampleNodes::total()),
            static fn (ClassNode $owner, MethodNode $method): Edge => new MethodEdge($owner, $method, SampleEdges::meta()),
        );

        self::assertCount(1, $start->graph->nodes());
    }

    public function testRelatedToGivesBothRootsToTheRelationWithTheirOwnTypes(): void
    {
        $result = GeneratedGraph::rootedAt(SampleNodes::invoice())->relatedTo(
            GeneratedGraph::rootedAt(SampleNodes::total()),
            static fn (ClassNode $owner, MethodNode $method): Edge => new MethodEdge($owner, $method, SampleEdges::meta()),
        );

        self::assertSame(
            'App\Domain\Invoice -> App\Domain\Invoice::total',
            $result->graph->authoredEdges()[0]->from()->toString().' -> '.$result->graph->authoredEdges()[0]->to()->toString(),
        );
    }
}
