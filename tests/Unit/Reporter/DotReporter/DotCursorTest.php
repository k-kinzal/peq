<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\DotReporter;

use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Declaration\MethodEdge;
use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Reporter\DotReporter\DotCursor;
use App\Reporter\Traversal\DepthFirstTraversal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DotCursor::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(\App\Analyzer\Graph\EdgeKind::class)]
#[UsesClass(MethodEdge::class)]
#[UsesClass(Edge\Inverse\DeclaredInEdge::class)]
#[UsesClass(MethodCallEdge::class)]
#[UsesClass(Edge\Inverse\UsedByEdge::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(Graph::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(ClassNode::class)]
#[UsesClass(MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[UsesClass(\App\Reporter\Continuation::class)]
#[UsesClass(\App\Reporter\Expansion::class)]
#[UsesClass(DepthFirstTraversal::class)]
#[Small]
final class DotCursorTest extends TestCase
{
    #[DataProvider('providerInvoiceGraph')]
    public function testVisitRecordsTheSymbolItIsGiven(Graph $graph): void
    {
        $cursor = new DotCursor($graph, new DepthFirstTraversal(Direction::Uses));

        $cursor->visit(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 0);

        self::assertSame(
            ['App\Domain\Invoice'],
            array_map(static fn (Node $node): string => $node->id()->toString(), $cursor->nodes()),
        );
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testVisitAsksTheWalkToContinueBelowAnExpandableSymbol(Graph $graph): void
    {
        $cursor = new DotCursor($graph, new DepthFirstTraversal(Direction::Uses));

        self::assertTrue($cursor->visit(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 0));
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testVisitRecordsNothingBelowTheBoundedLevel(Graph $graph): void
    {
        $cursor = new DotCursor($graph, new DepthFirstTraversal(Direction::Uses), 1);

        $cursor->visit(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 2);

        self::assertSame([], $cursor->nodes());
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testVisitRecordsASymbolOnceHoweverManyBranchesReachIt(Graph $graph): void
    {
        $cursor = new DotCursor($graph, new DepthFirstTraversal(Direction::Uses));
        $shared = new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true);

        $cursor->visit($shared, 1);
        $cursor->visit($shared, 1);

        self::assertCount(1, $cursor->nodes());
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testNodesIsEmptyBeforeTheWalkHasReachedAnything(Graph $graph): void
    {
        self::assertSame([], (new DotCursor($graph, new DepthFirstTraversal(Direction::Uses)))->nodes());
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testNodesKeepsTheOrderTheWalkReachedTheSymbolsIn(Graph $graph): void
    {
        $cursor = new DotCursor($graph, new DepthFirstTraversal(Direction::Uses));

        $cursor->visit(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 0);
        $cursor->visit(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), 1);
        $cursor->visit(new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true), 2);

        self::assertSame(
            ['App\Domain\Invoice', 'App\Domain\Invoice::total', 'App\Domain\Money::add'],
            array_map(static fn (Node $node): string => $node->id()->toString(), $cursor->nodes()),
        );
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testEdgesDrawsEveryRelationRunningBetweenTwoReachedSymbols(Graph $graph): void
    {
        $cursor = new DotCursor($graph, new DepthFirstTraversal(Direction::Uses));

        $cursor->visit(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 0);
        $cursor->visit(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), 1);
        $cursor->visit(new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true), 2);

        self::assertSame(
            [
                'App\Domain\Invoice declaration-method App\Domain\Invoice::total',
                'App\Domain\Invoice::total method-call App\Domain\Money::add',
            ],
            array_map(
                static fn (Edge $edge): string => sprintf('%s %s %s', $edge->from()->toString(), $edge->kind()->value, $edge->to()->toString()),
                $cursor->edges(),
            ),
        );
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testEdgesLeavesOutARelationPointingOutOfWhatTheWalkCovered(Graph $graph): void
    {
        $cursor = new DotCursor($graph, new DepthFirstTraversal(Direction::Uses));

        $cursor->visit(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 0);
        $cursor->visit(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), 1);

        self::assertSame(
            ['App\Domain\Invoice declaration-method App\Domain\Invoice::total'],
            array_map(
                static fn (Edge $edge): string => sprintf('%s %s %s', $edge->from()->toString(), $edge->kind()->value, $edge->to()->toString()),
                $cursor->edges(),
            ),
        );
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testEdgesReadsTheGraphTheWayItsTraversalDoes(Graph $graph): void
    {
        $cursor = new DotCursor($graph, new DepthFirstTraversal(Direction::UsedBy));

        $cursor->visit(new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true), 0);
        $cursor->visit(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), 1);

        self::assertSame(
            ['App\Domain\Money::add used-by App\Domain\Invoice::total'],
            array_map(
                static fn (Edge $edge): string => sprintf('%s %s %s', $edge->from()->toString(), $edge->kind()->value, $edge->to()->toString()),
                $cursor->edges(),
            ),
        );
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testEdgesDrawsNothingBeforeTheWalkHasReachedAnything(Graph $graph): void
    {
        self::assertSame([], (new DotCursor($graph, new DepthFirstTraversal(Direction::Uses)))->edges());
    }

    /**
     * @return iterable<string, array{Graph}>
     */
    public static function providerInvoiceGraph(): iterable
    {
        $meta = new FileMeta('/project/src/Domain/Invoice.php', 12, 1);
        $invoice = new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, $meta);
        $total = new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true, $meta);
        $lines = new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'lines'), true, $meta);
        $add = new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true, $meta);
        $graph = new Graph();
        $graph->addNodes([$invoice, $total, $lines, $add]);
        $graph->addEdges([
            new MethodEdge($invoice, $total, $meta),
            new MethodEdge($invoice, $lines, $meta),
            new MethodCallEdge($total, $add, $meta),
        ]);

        yield 'App\Domain\Invoice declares total and lines, and total calls App\Domain\Money::add' => [$graph];
    }
}
