<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\JsonReporter;

use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\Edge\Declaration\MethodEdge;
use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\Edge\Usage\StaticCallEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Reporter\JsonReporter\JsonCursor;
use App\Reporter\Traversal\DepthFirstTraversal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(JsonCursor::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(\App\Analyzer\Graph\EdgeKind::class)]
#[UsesClass(MethodEdge::class)]
#[UsesClass(StaticCallEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Inverse\DeclaredInEdge::class)]
#[UsesClass(MethodCallEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Inverse\UsedByEdge::class)]
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
#[UsesClass(\App\Reporter\CallOccurrences::class)]
#[Small]
final class JsonCursorTest extends TestCase
{
    #[DataProvider('providerInvoiceGraph')]
    public function testVisitRecordsTheSymbolItIsGiven(Graph $graph): void
    {
        $cursor = new JsonCursor($graph, new DepthFirstTraversal(Direction::Uses));

        $cursor->visit(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 0);

        self::assertSame('App\Domain\Invoice', $cursor->reached()[0]['id'] ?? null);
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testVisitAsksTheWalkToContinueBelowAnExpandableSymbol(Graph $graph): void
    {
        $cursor = new JsonCursor($graph, new DepthFirstTraversal(Direction::Uses));

        self::assertTrue($cursor->visit(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 0));
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testVisitRecordsNothingBelowTheBoundedLevel(Graph $graph): void
    {
        $cursor = new JsonCursor($graph, new DepthFirstTraversal(Direction::Uses), 1);

        self::assertFalse($cursor->visit(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 2));
        self::assertSame([], $cursor->reached());
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testVisitRecordsEverythingKnownAboutTheSymbolTheWalkStartsAt(Graph $graph): void
    {
        $cursor = new JsonCursor($graph, new DepthFirstTraversal(Direction::Uses));

        $cursor->visit(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 0);

        self::assertSame([
            'id' => 'App\Domain\Invoice',
            'kind' => 'class',
            'resolved' => true,
            'depth' => 0,
            'parent' => null,
            'relations' => [],
            'truncated' => null,
            'file' => null,
        ], $cursor->reached()[0] ?? null);
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testVisitRecordsEverythingKnownAboutASymbolHangingUnderAnother(Graph $graph): void
    {
        $cursor = new JsonCursor($graph, new DepthFirstTraversal(Direction::Uses));
        $meta = new FileMeta('/project/src/Domain/Invoice.php', 12, 1);

        $cursor->visit(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, $meta), 0);
        $cursor->visit(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), false, $meta), 1);

        self::assertSame([
            'id' => 'App\Domain\Invoice::total',
            'kind' => 'method',
            'resolved' => false,
            'depth' => 1,
            'parent' => 'App\Domain\Invoice',
            'relations' => ['declaration-method'],
            'truncated' => null,
            'file' => ['path' => '/project/src/Domain/Invoice.php', 'line' => 12, 'column' => 1],
        ], $cursor->reached()[1] ?? null);
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testVisitRecordsWhyABranchWasCut(Graph $graph): void
    {
        $cursor = new JsonCursor($graph, new DepthFirstTraversal(Direction::Uses));
        $root = new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true);

        $cursor->visit($root, 0);
        $cursor->visit(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), 1);
        $cursor->visit($root, 2);

        self::assertSame('recursive', $cursor->reached()[2]['truncated'] ?? null);
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testVisitRecordsWhereASymbolIsWritten(Graph $graph): void
    {
        $cursor = new JsonCursor($graph, new DepthFirstTraversal(Direction::Uses));

        $cursor->visit(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, new FileMeta('/project/src/Domain/Invoice.php', 12, 4)), 0);

        self::assertSame(
            ['path' => '/project/src/Domain/Invoice.php', 'line' => 12, 'column' => 4],
            $cursor->reached()[0]['file'] ?? null,
        );
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testRelationsNamesEveryRelationRunningBetweenTwoSymbols(Graph $graph): void
    {
        $cursor = new JsonCursor($graph, new DepthFirstTraversal(Direction::Uses));

        self::assertSame(
            ['method-call', 'static-call'],
            $cursor->relations(MethodNodeId::of('App\Domain\Invoice', 'total'), MethodNodeId::of('App\Domain\Money', 'add')),
        );
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testRelationsNamesNothingWhenNoRelationRunsThatWay(Graph $graph): void
    {
        $cursor = new JsonCursor($graph, new DepthFirstTraversal(Direction::Uses));

        self::assertSame(
            [],
            $cursor->relations(MethodNodeId::of('App\Domain\Money', 'add'), MethodNodeId::of('App\Domain\Invoice', 'total')),
        );
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testRelationsReadsTheGraphTheWayItsTraversalDoes(Graph $graph): void
    {
        $cursor = new JsonCursor($graph, new DepthFirstTraversal(Direction::UsedBy));

        self::assertSame(
            ['used-by'],
            $cursor->relations(MethodNodeId::of('App\Domain\Money', 'add'), MethodNodeId::of('App\Domain\Invoice', 'total')),
        );
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testReachedIsEmptyBeforeTheWalkHasReachedAnything(Graph $graph): void
    {
        self::assertSame([], (new JsonCursor($graph, new DepthFirstTraversal(Direction::Uses)))->reached());
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testReachedKeepsTheOrderTheWalkReachedTheSymbolsIn(Graph $graph): void
    {
        $cursor = new JsonCursor($graph, new DepthFirstTraversal(Direction::Uses));

        $cursor->visit(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 0);
        $cursor->visit(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), 1);
        $cursor->visit(new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true), 2);

        self::assertSame(
            ['App\Domain\Invoice', 'App\Domain\Invoice::total', 'App\Domain\Money::add'],
            array_column($cursor->reached(), 'id'),
        );
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
            new StaticCallEdge($total, $add, $meta),
        ]);

        yield 'App\Domain\Invoice declares total and lines, and total calls App\Domain\Money::add twice over' => [$graph];
    }
}
