<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph;

use App\Analyzer\Graph\Edge\Declaration\MethodEdge;
use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\Edge\Usage\StaticCallEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeKind;
use App\Analyzer\Graph\NodePrecedence;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[UsesClass(\App\Analyzer\Graph\EdgeIdentity::class)]
#[CoversClass(Graph::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(MethodEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Inverse\DeclaredInEdge::class)]
#[UsesClass(MethodCallEdge::class)]
#[UsesClass(StaticCallEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Inverse\UsedByEdge::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\UnknownNodeId::class)]
#[UsesClass(ClassNode::class)]
#[UsesClass(MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\UnknownNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[UsesClass(NodePrecedence::class)]
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
        $graph->addEdge(new MethodCallEdge(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true), new FileMeta('/project/src/Domain/Invoice.php', 12, 1)));
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
        $edge = new MethodCallEdge(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true), new FileMeta('/project/src/Domain/Invoice.php', 12, 1));
        $graph->addEdge($edge);

        self::assertSame($edge, $graph->edge($edge->from(), $edge->to(), EdgeKind::MethodCall));
    }

    public function testAddEdgeMakesTheRelationReadableFromItsTargetToo(): void
    {
        $graph = new Graph();
        $edge = new MethodCallEdge(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true), new FileMeta('/project/src/Domain/Invoice.php', 12, 1));
        $graph->addEdge($edge);

        self::assertSame(EdgeKind::UsedBy, $graph->edge($edge->to(), $edge->from())?->kind());
    }

    public function testAddEdgeRecordsPlaceholdersForSymbolsNotSeenYet(): void
    {
        $graph = new Graph();
        $graph->addEdge(new MethodCallEdge(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true), new FileMeta('/project/src/Domain/Invoice.php', 12, 1)));

        self::assertSame(NodeKind::Unknown, $graph->nodeNamed('App\Domain\Money::add')?->kind());
    }

    public function testAddNodeKeepsTheRelationsRecordedWhileTheSymbolWasAPlaceholder(): void
    {
        $graph = new Graph();
        $edge = new MethodCallEdge(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true), new FileMeta('/project/src/Domain/Invoice.php', 12, 1));
        $graph->addEdge($edge);
        $graph->addNode(new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true));

        self::assertCount(1, $graph->edges(MethodNodeId::of('App\Domain\Money', 'add')));
    }

    public function testAddEdgeRecordsAPlaceholderForTheSymbolARelationStartsAt(): void
    {
        $graph = new Graph();
        $graph->addEdge(new MethodCallEdge(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true), new FileMeta('/project/src/Domain/Invoice.php', 12, 1)));

        self::assertSame(NodeKind::Unknown, $graph->nodeNamed('App\Domain\Invoice::total')?->kind());
    }

    public function testAddEdgeKeepsTwoRelationsThatDifferOnlyInWhereTheyStart(): void
    {
        $graph = new Graph();
        $called = new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true);
        $graph->addEdge(new MethodCallEdge(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), $called, new FileMeta('/project/src/Domain/Invoice.php', 12, 1)));
        $graph->addEdge(new MethodCallEdge(new MethodNode(MethodNodeId::of('App\Domain\Receipt', 'total'), true), $called, new FileMeta('/project/src/Domain/Invoice.php', 12, 1)));

        self::assertCount(2, $graph->forwardEdges());
    }

    public function testAddEdgeKeepsTwoRelationsThatDifferOnlyInWhereTheyPointAt(): void
    {
        $graph = new Graph();
        $caller = new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true);
        $graph->addEdge(new MethodCallEdge($caller, new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true), new FileMeta('/project/src/Domain/Invoice.php', 12, 1)));
        $graph->addEdge(new MethodCallEdge($caller, new MethodNode(MethodNodeId::of('App\Domain\Money', 'subtract'), true), new FileMeta('/project/src/Domain/Invoice.php', 12, 1)));

        self::assertCount(2, $graph->forwardEdges());
    }

    public function testAddEdgeKeepsTwoRelationsThatDifferOnlyInTheirKind(): void
    {
        $graph = new Graph();
        $caller = new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true);
        $called = new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true);
        $graph->addEdge(new MethodCallEdge($caller, $called, new FileMeta('/project/src/Domain/Invoice.php', 12, 1)));
        $graph->addEdge(new StaticCallEdge($caller, $called, new FileMeta('/project/src/Domain/Invoice.php', 12, 1)));

        self::assertCount(2, $graph->forwardEdges());
    }

    public function testAddEdgeRecordsTheSameRelationOnlyOnce(): void
    {
        $graph = new Graph();
        $graph->addEdge(new MethodCallEdge(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true), new FileMeta('/project/src/Domain/Invoice.php', 12, 1)));
        $graph->addEdge(new MethodCallEdge(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true), new FileMeta('/project/src/Domain/Invoice.php', 12, 1)));

        self::assertCount(1, $graph->edges(MethodNodeId::of('App\Domain\Invoice', 'total')));
    }

    public function testAddEdgesRecordsEveryRelationItIsGiven(): void
    {
        $graph = new Graph();
        $graph->addEdges([
            new MethodCallEdge(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true), new FileMeta('/project/src/Domain/Invoice.php', 12, 1)),
            new MethodEdge(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), new FileMeta('/project/src/Domain/Invoice.php', 12, 1)),
        ]);

        self::assertCount(2, $graph->forwardEdges());
        self::assertSame(EdgeKind::UsedBy, $graph->edge(MethodNodeId::of('App\Domain\Money', 'add'), MethodNodeId::of('App\Domain\Invoice', 'total'))?->kind());
        self::assertSame(EdgeKind::DeclaredIn, $graph->edge(MethodNodeId::of('App\Domain\Invoice', 'total'), ClassNodeId::of('App\Domain\Invoice'))?->kind());
    }

    public function testEdgesReportsBothReadingsRecordedForASymbol(): void
    {
        $graph = new Graph();
        $graph->addEdge(new MethodEdge(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), new FileMeta('/project/src/Domain/Invoice.php', 12, 1)));

        self::assertCount(1, $graph->edges(MethodNodeId::of('App\Domain\Invoice', 'total')));
    }

    public function testEdgesReportsNothingForASymbolWithNoRelations(): void
    {
        self::assertSame([], (new Graph())->edges(ClassNodeId::of('App\Domain\Invoice')));
    }

    public function testEdgeCanBeAskedWithoutNamingAKind(): void
    {
        $graph = new Graph();
        $edge = new MethodCallEdge(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true), new FileMeta('/project/src/Domain/Invoice.php', 12, 1));
        $graph->addEdge($edge);

        self::assertSame($edge, $graph->edge($edge->from(), $edge->to()));
    }

    public function testEdgeFindsNothingForAKindThatWasNeverRecorded(): void
    {
        $graph = new Graph();
        $edge = new MethodCallEdge(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true), new FileMeta('/project/src/Domain/Invoice.php', 12, 1));
        $graph->addEdge($edge);

        self::assertNull($graph->edge($edge->from(), $edge->to(), EdgeKind::StaticCall));
    }

    public function testForwardEdgesLeavesOutTheDerivedReadings(): void
    {
        $graph = new Graph();
        $graph->addEdge(new MethodCallEdge(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true), new FileMeta('/project/src/Domain/Invoice.php', 12, 1)));

        self::assertCount(1, $graph->forwardEdges());
        self::assertInstanceOf(MethodCallEdge::class, $graph->forwardEdges()[0]);
    }

    public function testForwardEdgesIsEmptyForAGraphWithNoRelations(): void
    {
        self::assertSame([], (new Graph())->forwardEdges());
    }

    public function testMergeHoldsEverySymbolOfBothGraphs(): void
    {
        $first = new Graph();
        $first->addEdge(new MethodCallEdge(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true), new FileMeta('/project/src/Domain/Invoice.php', 12, 1)));
        $second = new Graph();
        $second->addEdge(new MethodEdge(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), new FileMeta('/project/src/Domain/Invoice.php', 12, 1)));

        $merged = $first->merge($second);

        $names = array_map(static fn (\App\Analyzer\Graph\Node $node): string => $node->id()->toString(), $merged->nodes());
        sort($names);

        self::assertSame(['App\Domain\Invoice', 'App\Domain\Invoice::total', 'App\Domain\Money::add'], $names);
    }

    public function testMergeDerivesTheReverseReadingsAgainRatherThanCarryingThem(): void
    {
        $first = new Graph();
        $first->addEdge(new MethodCallEdge(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true), new FileMeta('/project/src/Domain/Invoice.php', 12, 1)));

        $merged = $first->merge(new Graph());

        self::assertCount(1, $merged->forwardEdges());
        self::assertCount(1, $merged->edges(MethodNodeId::of('App\Domain\Money', 'add')));
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
        $graph->addEdge(new MethodCallEdge(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true), new FileMeta('/project/src/Domain/Invoice.php', 12, 1)));

        $merged = $graph->merge($graph);

        self::assertCount(count($graph->nodes()), $merged->nodes());
        self::assertCount(count($graph->forwardEdges()), $merged->forwardEdges());
    }

    public function testMergeKeepsSymbolsThatHaveNoRelationsOnEitherSide(): void
    {
        $first = new Graph();
        $first->addNode(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true));
        $second = new Graph();
        $second->addNode(new ClassNode(ClassNodeId::of('App\Domain\Money'), true));

        $merged = $first->merge($second);

        self::assertNotNull($merged->nodeNamed('App\Domain\Invoice'));
        self::assertNotNull($merged->nodeNamed('App\Domain\Money'));
    }

    public function testElementsYieldsNodesBeforeForwardEdgesWithoutInverseEdges(): void
    {
        $first = new MethodNode(MethodNodeId::of('Service', 'run'));
        $second = new MethodNode(MethodNodeId::of('Service', 'step'));
        $edge = new MethodCallEdge($first, $second, new FileMeta('/project/Service.php', 3, 1));
        $graph = new Graph();
        $graph->addNodes([$first, $second]);
        $graph->addEdge($edge);

        self::assertSame([$first, $second, $edge], iterator_to_array($graph->elements(), false));
    }
}
