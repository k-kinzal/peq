<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Traversal;

use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Reporter\Traversal\DepthFirstTraversal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Graph\SampleGraph;
use Tests\Fixture\Reporter\VisitLog;

/**
 * @internal
 */
#[CoversClass(DepthFirstTraversal::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(\App\Analyzer\Graph\EdgeKind::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\DeclarationMethodEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\DeclaredInEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\MethodCallEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\UsedByEdge::class)]
#[UsesClass(\App\Analyzer\Graph\FileMeta::class)]
#[UsesClass(\App\Analyzer\Graph\Graph::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\Node\ClassNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[UsesClass(\App\Reporter\Traversal\DepthFirstWalk::class)]
#[Small]
final class DepthFirstTraversalTest extends TestCase
{
    #[DataProvider('providerBothDirections')]
    public function testDirectionReportsTheOneItWasGiven(Direction $direction): void
    {
        self::assertSame($direction, (new DepthFirstTraversal($direction))->direction());
    }

    public function testTraverseStartsAtTheSymbolItWasAskedAbout(): void
    {
        $log = new VisitLog();
        (new DepthFirstTraversal(Direction::Uses))
            ->traverse(SampleGraph::invoice(), ClassNodeId::of('App\Domain\Invoice'), $log->recorder())
        ;

        self::assertSame('App\Domain\Invoice', $log->names()[0]);
    }

    public function testTraverseReadsTheGraphAwayFromTheSubject(): void
    {
        $log = new VisitLog();
        (new DepthFirstTraversal(Direction::Uses))
            ->traverse(SampleGraph::invoice(), ClassNodeId::of('App\Domain\Invoice'), $log->recorder())
        ;

        self::assertSame([
            'App\Domain\Invoice',
            'App\Domain\Invoice::total',
            'App\Domain\Money::add',
            'App\Domain\Invoice::lines',
        ], $log->names());
    }

    public function testTraverseReadsTheGraphTowardsTheSubject(): void
    {
        $log = new VisitLog();
        (new DepthFirstTraversal(Direction::UsedBy))
            ->traverse(SampleGraph::invoice(), MethodNodeId::of('App\Domain\Money', 'add'), $log->recorder())
        ;

        self::assertSame([
            'App\Domain\Money::add',
            'App\Domain\Invoice::total',
            'App\Domain\Invoice',
        ], $log->names());
    }

    #[DataProvider('providerBothDirections')]
    public function testTraverseTerminatesOnACyclicGraph(Direction $direction): void
    {
        $log = new VisitLog();
        (new DepthFirstTraversal($direction))
            ->traverse(SampleGraph::cyclic(), MethodNodeId::of('App\Domain\Invoice', 'total'), $log->recorder())
        ;

        self::assertCount(3, $log->names());
    }

    #[DataProvider('providerBothDirections')]
    public function testTraverseIsSafeToRunTwiceWithTheSameStrategy(Direction $direction): void
    {
        $traversal = new DepthFirstTraversal($direction);
        $first = new VisitLog();
        $second = new VisitLog();

        $traversal->traverse(SampleGraph::invoice(), ClassNodeId::of('App\Domain\Invoice'), $first->recorder());
        $traversal->traverse(SampleGraph::invoice(), ClassNodeId::of('App\Domain\Invoice'), $second->recorder());

        self::assertSame($first->names(), $second->names());
    }

    /**
     * @return iterable<string, array{Direction}>
     */
    public static function providerBothDirections(): iterable
    {
        yield 'away from the subject' => [Direction::Uses];

        yield 'towards the subject' => [Direction::UsedBy];
    }
}
