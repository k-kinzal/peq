<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Traversal;

use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\Edge\Declaration\MethodEdge;
use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Reporter\Traversal\DepthFirstWalk;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DepthFirstWalk::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(\App\Analyzer\Graph\EdgeKind::class)]
#[UsesClass(MethodEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Inverse\DeclaredInEdge::class)]
#[UsesClass(MethodCallEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Inverse\UsedByEdge::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(Graph::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(Node\ClassNode::class)]
#[UsesClass(Node\MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class DepthFirstWalkTest extends TestCase
{
    #[DataProvider('providerInvoiceGraph')]
    public function testVisitHandsOverTheSymbolItWasStartedAt(Graph $graph): void
    {
        $visited = [];
        (new DepthFirstWalk($graph, Direction::Uses, static function (Node $node) use (&$visited): bool {
            $visited[] = $node->id()->toString();

            return true;
        }))->visit(ClassNodeId::of('App\Domain\Invoice'), 0);

        self::assertSame('App\Domain\Invoice', $visited[0]);
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testVisitDescendsIntoTheRelationsOfItsOwnDirection(Graph $graph): void
    {
        $visited = [];
        (new DepthFirstWalk($graph, Direction::Uses, static function (Node $node) use (&$visited): bool {
            $visited[] = $node->id()->toString();

            return true;
        }))->visit(ClassNodeId::of('App\Domain\Invoice'), 0);

        self::assertSame(['App\Domain\Invoice', 'App\Domain\Invoice::total', 'App\Domain\Money::add', 'App\Domain\Invoice::lines'], $visited);
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testVisitLeavesTheRelationsOfTheOtherDirectionAlone(Graph $graph): void
    {
        $visited = [];
        (new DepthFirstWalk($graph, Direction::UsedBy, static function (Node $node) use (&$visited): bool {
            $visited[] = $node->id()->toString();

            return true;
        }))->visit(ClassNodeId::of('App\Domain\Invoice'), 0);

        self::assertSame(['App\Domain\Invoice'], $visited);
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testVisitReadsTowardsTheSubjectInTheOtherDirection(Graph $graph): void
    {
        $visited = [];
        (new DepthFirstWalk($graph, Direction::UsedBy, static function (Node $node) use (&$visited): bool {
            $visited[] = $node->id()->toString();

            return true;
        }))->visit(MethodNodeId::of('App\Domain\Money', 'add'), 0);

        self::assertSame(['App\Domain\Money::add', 'App\Domain\Invoice::total', 'App\Domain\Invoice'], $visited);
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testVisitCountsHowFarBelowTheStartEachSymbolSits(Graph $graph): void
    {
        $depths = [];
        (new DepthFirstWalk($graph, Direction::Uses, static function (Node $node, int $depth) use (&$depths): bool {
            $depths[] = [$node->id()->toString(), $depth];

            return true;
        }))->visit(ClassNodeId::of('App\Domain\Invoice'), 0);

        self::assertSame([
            ['App\Domain\Invoice', 0],
            ['App\Domain\Invoice::total', 1],
            ['App\Domain\Money::add', 2],
            ['App\Domain\Invoice::lines', 1],
        ], $depths);
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testVisitStopsWhereTheVisitorSaysNotToDescend(Graph $graph): void
    {
        $visited = [];
        (new DepthFirstWalk($graph, Direction::Uses, static function (Node $node, int $depth) use (&$visited): bool {
            $visited[] = $node->id()->toString();

            return $depth < 0;
        }))->visit(ClassNodeId::of('App\Domain\Invoice'), 0);

        self::assertSame(['App\Domain\Invoice'], $visited);
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testVisitDescendsOneLevelWhenTheVisitorAllowsOne(Graph $graph): void
    {
        $visited = [];
        (new DepthFirstWalk($graph, Direction::Uses, static function (Node $node, int $depth) use (&$visited): bool {
            $visited[] = $node->id()->toString();

            return $depth < 1;
        }))->visit(ClassNodeId::of('App\Domain\Invoice'), 0);

        self::assertSame(['App\Domain\Invoice', 'App\Domain\Invoice::total', 'App\Domain\Invoice::lines'], $visited);
    }

    #[DataProvider('providerCyclicGraph')]
    public function testVisitHandsOverASymbolThatClosesACycleWithoutDescendingAgain(Graph $graph): void
    {
        $visited = [];
        (new DepthFirstWalk($graph, Direction::Uses, static function (Node $node) use (&$visited): bool {
            $visited[] = $node->id()->toString();

            return true;
        }))->visit(MethodNodeId::of('App\Domain\Invoice', 'total'), 0);

        self::assertSame(['App\Domain\Invoice::total', 'App\Domain\Money::add', 'App\Domain\Invoice::total'], $visited);
    }

    /**
     * @return iterable<string, array{Graph}>
     */
    public static function providerCyclicGraph(): iterable
    {
        $meta = new FileMeta('/project/src/Domain/Invoice.php', 12, 1);
        $total = new Node\MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true, $meta);
        $add = new Node\MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true, $meta);
        $graph = new Graph();
        $graph->addNodes([$total, $add]);
        $graph->addEdges([
            new MethodCallEdge($total, $add, $meta),
            new MethodCallEdge($add, $total, $meta),
        ]);

        yield 'App\Domain\Invoice::total and App\Domain\Money::add call each other' => [$graph];
    }

    #[DataProvider('providerSharedDependencyGraph')]
    public function testVisitDescendsIntoASharedSymbolOncePerRelationThatReachesIt(Graph $graph): void
    {
        $visited = [];
        (new DepthFirstWalk($graph, Direction::Uses, static function (Node $node) use (&$visited): bool {
            $visited[] = $node->id()->toString();

            return true;
        }))->visit(ClassNodeId::of('App\Domain\Invoice'), 0);

        self::assertSame([
            'App\Domain\Invoice',
            'App\Domain\Invoice::total',
            'App\Domain\Money::add',
            'App\Domain\Invoice::lines',
            'App\Domain\Money::add',
        ], $visited);
    }

    /**
     * @return iterable<string, array{Graph}>
     */
    public static function providerSharedDependencyGraph(): iterable
    {
        $meta = new FileMeta('/project/src/Domain/Invoice.php', 12, 1);
        $invoice = new Node\ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, $meta);
        $total = new Node\MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true, $meta);
        $lines = new Node\MethodNode(MethodNodeId::of('App\Domain\Invoice', 'lines'), true, $meta);
        $add = new Node\MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true, $meta);
        $graph = new Graph();
        $graph->addNodes([$invoice, $total, $lines, $add]);
        $graph->addEdges([
            new MethodEdge($invoice, $total, $meta),
            new MethodEdge($invoice, $lines, $meta),
            new MethodCallEdge($total, $add, $meta),
            new MethodCallEdge($lines, $add, $meta),
        ]);

        yield 'App\Domain\Invoice::total and lines both call App\Domain\Money::add' => [$graph];
    }

    public function testVisitReportsNothingForASymbolTheGraphDoesNotHold(): void
    {
        $visited = [];
        (new DepthFirstWalk(new Graph(), Direction::Uses, static function (Node $node) use (&$visited): bool {
            $visited[] = $node->id()->toString();

            return true;
        }))->visit(ClassNodeId::of('App\Domain\Missing'), 0);

        self::assertSame([], $visited);
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testVisitCanBeStartedBelowTheTopOfTheReport(Graph $graph): void
    {
        $depths = [];
        (new DepthFirstWalk($graph, Direction::Uses, static function (Node $node, int $depth) use (&$depths): bool {
            $depths[] = $depth;

            return true;
        }))->visit(ClassNodeId::of('App\Domain\Invoice'), 7);

        self::assertSame([7, 8, 9, 8], $depths);
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testTheVisitorIsGivenTheNodeTheGraphHolds(Graph $graph): void
    {
        $nodes = [];
        (new DepthFirstWalk($graph, Direction::Uses, static function (Node $node) use (&$nodes): bool {
            $nodes[] = $node;

            return true;
        }))->visit(ClassNodeId::of('App\Domain\Invoice'), 0);

        self::assertSame($graph->nodeNamed('App\Domain\Invoice'), $nodes[0]);
    }

    /**
     * @return iterable<string, array{Graph}>
     */
    public static function providerInvoiceGraph(): iterable
    {
        $meta = new FileMeta('/project/src/Domain/Invoice.php', 12, 1);
        $invoice = new Node\ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, $meta);
        $total = new Node\MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true, $meta);
        $lines = new Node\MethodNode(MethodNodeId::of('App\Domain\Invoice', 'lines'), true, $meta);
        $add = new Node\MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true, $meta);
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
