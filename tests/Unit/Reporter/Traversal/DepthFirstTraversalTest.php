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
use App\Reporter\Traversal\DepthFirstTraversal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DepthFirstTraversal::class)]
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
#[UsesClass(\App\Reporter\Traversal\DepthFirstWalk::class)]
#[Small]
final class DepthFirstTraversalTest extends TestCase
{
    public function testDirectionReportsAwayFromTheSubjectWhenBuiltThatWay(): void
    {
        self::assertSame(Direction::Uses, (new DepthFirstTraversal(Direction::Uses))->direction());
    }

    public function testDirectionReportsTowardsTheSubjectWhenBuiltThatWay(): void
    {
        self::assertSame(Direction::UsedBy, (new DepthFirstTraversal(Direction::UsedBy))->direction());
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testTraverseReadsTheGraphAwayFromTheSubject(Graph $graph): void
    {
        $visited = [];
        (new DepthFirstTraversal(Direction::Uses))->traverse($graph, ClassNodeId::of('App\Domain\Invoice'), static function (Node $node) use (&$visited): bool {
            $visited[] = $node->id()->toString();

            return true;
        });

        self::assertSame(['App\Domain\Invoice', 'App\Domain\Invoice::total', 'App\Domain\Money::add', 'App\Domain\Invoice::lines'], $visited);
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testTraverseReadsTheGraphTowardsTheSubject(Graph $graph): void
    {
        $visited = [];
        (new DepthFirstTraversal(Direction::UsedBy))->traverse($graph, MethodNodeId::of('App\Domain\Money', 'add'), static function (Node $node) use (&$visited): bool {
            $visited[] = $node->id()->toString();

            return true;
        });

        self::assertSame(['App\Domain\Money::add', 'App\Domain\Invoice::total', 'App\Domain\Invoice'], $visited);
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testTraverseStartsCountingDepthAtZero(Graph $graph): void
    {
        $depths = [];
        (new DepthFirstTraversal(Direction::Uses))->traverse($graph, ClassNodeId::of('App\Domain\Invoice'), static function (Node $node, int $depth) use (&$depths): bool {
            $depths[] = $depth;

            return true;
        });

        self::assertSame([0, 1, 2, 1], $depths);
    }

    #[DataProvider('providerCyclicGraph')]
    public function testTraverseTerminatesOnACyclicGraphReadAwayFromTheSubject(Graph $graph): void
    {
        $visited = [];
        (new DepthFirstTraversal(Direction::Uses))->traverse($graph, MethodNodeId::of('App\Domain\Invoice', 'total'), static function (Node $node) use (&$visited): bool {
            $visited[] = $node->id()->toString();

            return true;
        });

        self::assertSame(['App\Domain\Invoice::total', 'App\Domain\Money::add', 'App\Domain\Invoice::total'], $visited);
    }

    #[DataProvider('providerCyclicGraph')]
    public function testTraverseTerminatesOnACyclicGraphReadTowardsTheSubject(Graph $graph): void
    {
        $visited = [];
        (new DepthFirstTraversal(Direction::UsedBy))->traverse($graph, MethodNodeId::of('App\Domain\Invoice', 'total'), static function (Node $node) use (&$visited): bool {
            $visited[] = $node->id()->toString();

            return true;
        });

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

    #[DataProvider('providerInvoiceGraph')]
    public function testTraverseIsSafeToRunTwiceWithTheSameStrategy(Graph $graph): void
    {
        $traversal = new DepthFirstTraversal(Direction::Uses);
        $first = [];
        $second = [];

        $traversal->traverse($graph, ClassNodeId::of('App\Domain\Invoice'), static function (Node $node) use (&$first): bool {
            $first[] = $node->id()->toString();

            return true;
        });
        $traversal->traverse($graph, ClassNodeId::of('App\Domain\Invoice'), static function (Node $node) use (&$second): bool {
            $second[] = $node->id()->toString();

            return true;
        });

        self::assertSame(['App\Domain\Invoice', 'App\Domain\Invoice::total', 'App\Domain\Money::add', 'App\Domain\Invoice::lines'], $second);
        self::assertSame($first, $second);
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
