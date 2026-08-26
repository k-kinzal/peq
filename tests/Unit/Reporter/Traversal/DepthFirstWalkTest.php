<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Traversal;

use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Reporter\Traversal\DepthFirstWalk;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Graph\SampleGraph;
use Tests\Fixture\Reporter\VisitLog;

/**
 * @internal
 */
#[CoversClass(DepthFirstWalk::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(\App\Analyzer\Graph\EdgeKind::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Declaration\MethodEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Inverse\DeclaredInEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Usage\MethodCallEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Inverse\UsedByEdge::class)]
#[UsesClass(\App\Analyzer\Graph\FileMeta::class)]
#[UsesClass(Graph::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(Node\ClassNode::class)]
#[UsesClass(Node\MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class DepthFirstWalkTest extends TestCase
{
    public function testVisitHandsOverTheSymbolItWasStartedAt(): void
    {
        $log = new VisitLog();
        (new DepthFirstWalk(SampleGraph::invoice(), Direction::Uses, $log->recorder()))
            ->visit(ClassNodeId::of('App\Domain\Invoice'), 0)
        ;

        self::assertSame('App\Domain\Invoice', $log->names()[0]);
    }

    public function testVisitDescendsIntoTheRelationsOfItsOwnDirection(): void
    {
        $log = new VisitLog();
        (new DepthFirstWalk(SampleGraph::invoice(), Direction::Uses, $log->recorder()))
            ->visit(ClassNodeId::of('App\Domain\Invoice'), 0)
        ;

        self::assertContains('App\Domain\Invoice::total', $log->names());
        self::assertContains('App\Domain\Money::add', $log->names());
    }

    public function testVisitLeavesTheRelationsOfTheOtherDirectionAlone(): void
    {
        $log = new VisitLog();
        (new DepthFirstWalk(SampleGraph::invoice(), Direction::UsedBy, $log->recorder()))
            ->visit(ClassNodeId::of('App\Domain\Invoice'), 0)
        ;

        self::assertSame(['App\Domain\Invoice'], $log->names());
    }

    public function testVisitReadsTowardsTheSubjectInTheOtherDirection(): void
    {
        $log = new VisitLog();
        (new DepthFirstWalk(SampleGraph::invoice(), Direction::UsedBy, $log->recorder()))
            ->visit(MethodNodeId::of('App\Domain\Money', 'add'), 0)
        ;

        self::assertContains('App\Domain\Invoice::total', $log->names());
    }

    public function testVisitCountsHowFarBelowTheStartEachSymbolSits(): void
    {
        $log = new VisitLog();
        (new DepthFirstWalk(SampleGraph::invoice(), Direction::Uses, $log->recorder()))
            ->visit(ClassNodeId::of('App\Domain\Invoice'), 0)
        ;

        self::assertSame(0, $log->depthOf('App\Domain\Invoice'));
        self::assertSame(1, $log->depthOf('App\Domain\Invoice::total'));
        self::assertSame(2, $log->depthOf('App\Domain\Money::add'));
    }

    public function testVisitStopsWhereTheVisitorSaysNotToDescend(): void
    {
        $log = new VisitLog();
        (new DepthFirstWalk(SampleGraph::invoice(), Direction::Uses, $log->recorderStoppingBelow(0)))
            ->visit(ClassNodeId::of('App\Domain\Invoice'), 0)
        ;

        self::assertSame(['App\Domain\Invoice'], $log->names());
    }

    public function testVisitHandsOverASymbolThatClosesACycleWithoutDescendingAgain(): void
    {
        $log = new VisitLog();
        (new DepthFirstWalk(SampleGraph::cyclic(), Direction::Uses, $log->recorder()))
            ->visit(MethodNodeId::of('App\Domain\Invoice', 'total'), 0)
        ;

        self::assertSame([
            'App\Domain\Invoice::total',
            'App\Domain\Money::add',
            'App\Domain\Invoice::total',
        ], $log->names());
    }

    public function testVisitDescendsIntoASharedSymbolOncePerRelationThatReachesIt(): void
    {
        $log = new VisitLog();
        (new DepthFirstWalk(SampleGraph::sharedDependency(), Direction::Uses, $log->recorder()))
            ->visit(ClassNodeId::of('App\Domain\Invoice'), 0)
        ;

        self::assertSame(2, count(array_filter($log->names(), static fn (string $name): bool => $name === 'App\Domain\Money::add')));
    }

    public function testVisitReportsNothingForASymbolTheGraphDoesNotHold(): void
    {
        $log = new VisitLog();
        (new DepthFirstWalk(new Graph(), Direction::Uses, $log->recorder()))
            ->visit(ClassNodeId::of('App\Domain\Missing'), 0)
        ;

        self::assertSame([], $log->names());
    }

    public function testVisitCanBeStartedBelowTheTopOfTheReport(): void
    {
        $log = new VisitLog();
        (new DepthFirstWalk(SampleGraph::invoice(), Direction::Uses, $log->recorder()))
            ->visit(ClassNodeId::of('App\Domain\Invoice'), 7)
        ;

        self::assertSame(7, $log->depthOf('App\Domain\Invoice'));
    }

    public function testTheVisitorIsGivenTheNodeItselfRatherThanItsName(): void
    {
        $log = new VisitLog();
        (new DepthFirstWalk(SampleGraph::invoice(), Direction::Uses, $log->recorder()))
            ->visit(ClassNodeId::of('App\Domain\Invoice'), 0)
        ;

        self::assertContainsOnlyInstancesOf(Node::class, $log->nodes());
    }
}
