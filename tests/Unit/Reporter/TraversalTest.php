<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter;

use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\Edge\Declaration\MethodEdge;
use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Reporter\Traversal;
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
#[UsesClass(Traversal\DepthFirstWalk::class)]
#[Small]
final class TraversalTest extends TestCase
{
    #[DataProvider('providerEveryTraversal')]
    public function testDirectionIsPartOfTheStrategyRatherThanOfEachCall(Traversal $traversal, Direction $direction, Graph $graph): void
    {
        $traversal->traverse($graph, ClassNodeId::of('App\Domain\Invoice'), static fn (): bool => true);

        self::assertSame($direction, $traversal->direction());
    }

    #[DataProvider('providerEveryTraversal')]
    public function testTraverseHandsOverTheStartingSymbolFirst(Traversal $traversal, Direction $direction, Graph $graph): void
    {
        $visited = [];
        $traversal->traverse($graph, ClassNodeId::of('App\Domain\Invoice'), static function (Node $node) use (&$visited): bool {
            $visited[] = $node->id()->toString();

            return true;
        });

        self::assertSame('App\Domain\Invoice', $visited[0]);
        self::assertSame($direction, $traversal->direction());
    }

    #[DataProvider('providerEveryTraversal')]
    public function testTraverseHandsOverOnlySymbolsTheGraphHolds(Traversal $traversal, Direction $direction, Graph $graph): void
    {
        $nodes = [];
        $traversal->traverse($graph, ClassNodeId::of('App\Domain\Invoice'), static function (Node $node) use (&$nodes): bool {
            $nodes[] = $node;

            return true;
        });

        self::assertSame($graph->nodeNamed('App\Domain\Invoice'), $nodes[0]);
        self::assertSame($direction, $traversal->direction());
    }

    #[DataProvider('providerEveryTraversal')]
    public function testTraverseStopsWhereTheVisitorSaysNotToDescend(Traversal $traversal, Direction $direction, Graph $graph): void
    {
        $visited = [];
        $traversal->traverse($graph, ClassNodeId::of('App\Domain\Invoice'), static function (Node $node) use (&$visited): bool {
            $visited[] = $node->id()->toString();

            return false;
        });

        self::assertSame(['App\Domain\Invoice'], $visited);
        self::assertSame($direction, $traversal->direction());
    }

    /**
     * @return iterable<string, array{Traversal, Direction, Graph}>
     */
    public static function providerEveryTraversal(): iterable
    {
        $meta = new FileMeta('/project/src/Domain/Invoice.php', 12, 1);
        $invoice = new Node\ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, $meta);
        $total = new Node\MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true, $meta);
        $add = new Node\MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true, $meta);
        $graph = new Graph();
        $graph->addNodes([$invoice, $total, $add]);
        $graph->addEdges([
            new MethodEdge($invoice, $total, $meta),
            new MethodCallEdge($total, $add, $meta),
        ]);

        yield 'depth first, away from the subject' => [new DepthFirstTraversal(Direction::Uses), Direction::Uses, $graph];

        yield 'depth first, towards the subject' => [new DepthFirstTraversal(Direction::UsedBy), Direction::UsedBy, $graph];
    }
}
