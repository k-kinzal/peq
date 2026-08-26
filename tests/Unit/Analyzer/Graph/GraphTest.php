<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph;

use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\Edge\Usage\StaticCallEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Graph\GraphInvariants;
use Tests\Fixture\Graph\SampleEdges;

/**
 * @internal
 */
#[CoversClass(Graph::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Declaration\MethodEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Inverse\DeclaredInEdge::class)]
#[UsesClass(MethodCallEdge::class)]
#[UsesClass(StaticCallEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Inverse\UsedByEdge::class)]
#[UsesClass(\App\Analyzer\Graph\FileMeta::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\UnknownNodeId::class)]
#[UsesClass(ClassNode::class)]
#[UsesClass(MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\UnknownNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class GraphTest extends TestCase
{
    public function testNodeFindsWhatWasRecordedUnderThatIdentifier(): void
    {
        $graph = new Graph();
        $node = new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true);
        $graph->addNode($node);

        self::assertSame($node, $graph->node(ClassNodeId::of('App\Domain\Invoice')));
    }

    public function testNodeFindsNothingForAnIdentifierNeverRecorded(): void
    {
        self::assertNull((new Graph())->node(ClassNodeId::of('App\Domain\Invoice')));
    }

    public function testNodeNamedFindsASymbolByTheNameItIsWrittenAs(): void
    {
        $graph = new Graph();
        $node = new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true);
        $graph->addNode($node);

        self::assertSame($node, $graph->nodeNamed('App\Domain\Invoice::total'));
    }

    public function testNodeNamedFindsNothingForANameTheGraphDoesNotHold(): void
    {
        self::assertNull((new Graph())->nodeNamed('App\Domain\Invoice'));
    }

    public function testAddNodeIgnoresASecondDescriptionOfTheSameSymbol(): void
    {
        $graph = new Graph();
        $first = new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true);
        $graph->addNode($first);
        $graph->addNode(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), false));

        self::assertSame($first, $graph->nodeNamed('App\Domain\Invoice'));
    }

    public function testAddNodeReplacesAPlaceholderWithTheRealSymbol(): void
    {
        $graph = new Graph();
        $graph->addEdge(SampleEdges::methodCall());
        $graph->addNode(new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true));

        self::assertSame(NodeKind::Method, $graph->nodeNamed('App\Domain\Money::add')?->kind());
    }

    public function testAddNodesRecordsEveryNodeItIsGiven(): void
    {
        $graph = new Graph();
        $graph->addNodes([
            new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true),
            new ClassNode(ClassNodeId::of('App\Domain\Money'), true),
        ]);

        self::assertCount(2, $graph->nodes());
    }

    public function testNodesReportsEverySymbolRecordedOnce(): void
    {
        $graph = new Graph();
        $graph->addNodes([
            new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true),
            new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true),
        ]);

        self::assertCount(1, $graph->nodes());
    }

    public function testAddEdgeMakesTheRelationReadableFromItsSource(): void
    {
        $graph = new Graph();
        $edge = SampleEdges::methodCall();
        $graph->addEdge($edge);

        self::assertSame($edge, $graph->edge($edge->from(), $edge->to(), EdgeKind::MethodCall));
    }

    public function testAddEdgeMakesTheRelationReadableFromItsTargetToo(): void
    {
        $graph = new Graph();
        $edge = SampleEdges::methodCall();
        $graph->addEdge($edge);

        self::assertSame(EdgeKind::UsedBy, $graph->edge($edge->to(), $edge->from())?->kind());
    }

    public function testAddEdgeRecordsPlaceholdersForSymbolsNotSeenYet(): void
    {
        $graph = new Graph();
        $graph->addEdge(SampleEdges::methodCall());

        self::assertSame(NodeKind::Unknown, $graph->nodeNamed('App\Domain\Money::add')?->kind());
    }

    public function testAddNodeKeepsTheRelationsRecordedWhileTheSymbolWasAPlaceholder(): void
    {
        $graph = new Graph();
        $edge = SampleEdges::methodCall();
        $graph->addEdge($edge);
        $graph->addNode(new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true));

        self::assertCount(1, $graph->edges(MethodNodeId::of('App\Domain\Money', 'add')));
    }

    public function testAddEdgeRecordsAPlaceholderForTheSymbolARelationStartsAt(): void
    {
        $graph = new Graph();
        $graph->addEdge(SampleEdges::methodCall());

        self::assertSame(NodeKind::Unknown, $graph->nodeNamed('App\Domain\Invoice::total')?->kind());
    }

    public function testAddEdgeKeepsTwoRelationsThatDifferOnlyInWhereTheyStart(): void
    {
        $graph = new Graph();
        $called = new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true);
        $graph->addEdge(new MethodCallEdge(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), $called, SampleEdges::meta()));
        $graph->addEdge(new MethodCallEdge(new MethodNode(MethodNodeId::of('App\Domain\Receipt', 'total'), true), $called, SampleEdges::meta()));

        self::assertCount(2, $graph->authoredEdges());
    }

    public function testAddEdgeKeepsTwoRelationsThatDifferOnlyInWhereTheyPointAt(): void
    {
        $graph = new Graph();
        $caller = new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true);
        $graph->addEdge(new MethodCallEdge($caller, new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true), SampleEdges::meta()));
        $graph->addEdge(new MethodCallEdge($caller, new MethodNode(MethodNodeId::of('App\Domain\Money', 'subtract'), true), SampleEdges::meta()));

        self::assertCount(2, $graph->authoredEdges());
    }

    public function testAddEdgeKeepsTwoRelationsThatDifferOnlyInTheirKind(): void
    {
        $graph = new Graph();
        $caller = new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true);
        $called = new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true);
        $graph->addEdge(new MethodCallEdge($caller, $called, SampleEdges::meta()));
        $graph->addEdge(new StaticCallEdge($caller, $called, SampleEdges::meta()));

        self::assertCount(2, $graph->authoredEdges());
    }

    public function testAddEdgeRecordsTheSameRelationOnlyOnce(): void
    {
        $graph = new Graph();
        $graph->addEdge(SampleEdges::methodCall());
        $graph->addEdge(SampleEdges::methodCall());

        self::assertCount(1, $graph->edges(SampleEdges::methodCall()->from()));
    }

    public function testAddEdgesRecordsEveryRelationItIsGiven(): void
    {
        $graph = new Graph();
        $graph->addEdges([SampleEdges::methodCall(), SampleEdges::methodDeclaration()]);

        GraphInvariants::assertBidirectional($graph);
        self::assertCount(2, $graph->authoredEdges());
    }

    public function testEdgesReportsBothReadingsRecordedForASymbol(): void
    {
        $graph = new Graph();
        $graph->addEdge(SampleEdges::methodDeclaration());

        self::assertCount(1, $graph->edges(SampleEdges::methodDeclaration()->to()));
    }

    public function testEdgesReportsNothingForASymbolWithNoRelations(): void
    {
        self::assertSame([], (new Graph())->edges(ClassNodeId::of('App\Domain\Invoice')));
    }

    public function testEdgeCanBeAskedWithoutNamingAKind(): void
    {
        $graph = new Graph();
        $edge = SampleEdges::methodCall();
        $graph->addEdge($edge);

        self::assertSame($edge, $graph->edge($edge->from(), $edge->to()));
    }

    public function testEdgeFindsNothingForAKindThatWasNeverRecorded(): void
    {
        $graph = new Graph();
        $edge = SampleEdges::methodCall();
        $graph->addEdge($edge);

        self::assertNull($graph->edge($edge->from(), $edge->to(), EdgeKind::StaticCall));
    }

    public function testAuthoredEdgesLeavesOutTheDerivedReadings(): void
    {
        $graph = new Graph();
        $graph->addEdge(SampleEdges::methodCall());

        self::assertCount(1, $graph->authoredEdges());
        self::assertInstanceOf(MethodCallEdge::class, $graph->authoredEdges()[0]);
    }

    public function testAuthoredEdgesIsEmptyForAGraphWithNoRelations(): void
    {
        self::assertSame([], (new Graph())->authoredEdges());
    }

    public function testMergeHoldsEverySymbolOfBothGraphs(): void
    {
        $first = new Graph();
        $first->addEdge(SampleEdges::methodCall());
        $second = new Graph();
        $second->addEdge(SampleEdges::methodDeclaration());

        $merged = $first->merge($second);

        GraphInvariants::assertAllNodesPreserved($first, $merged);
        GraphInvariants::assertAllNodesPreserved($second, $merged);
    }

    public function testMergeDerivesTheReverseReadingsAgainRatherThanCarryingThem(): void
    {
        $first = new Graph();
        $first->addEdge(SampleEdges::methodCall());

        $merged = $first->merge(new Graph());

        self::assertCount(1, $merged->authoredEdges());
        GraphInvariants::assertNoEdgeDuplicates($merged);
    }

    public function testMergeLeavesBothGraphsItWasBuiltFromUntouched(): void
    {
        $first = new Graph();
        $first->addNode(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true));
        $second = new Graph();
        $second->addNode(new ClassNode(ClassNodeId::of('App\Domain\Money'), true));

        $first->merge($second);

        self::assertNull($first->nodeNamed('App\Domain\Money'));
        self::assertNull($second->nodeNamed('App\Domain\Invoice'));
    }

    public function testMergingAGraphWithItselfChangesNothing(): void
    {
        $graph = new Graph();
        $graph->addEdge(SampleEdges::methodCall());

        $merged = $graph->merge($graph);

        self::assertCount(count($graph->nodes()), $merged->nodes());
        self::assertCount(count($graph->authoredEdges()), $merged->authoredEdges());
    }
}
