<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter;

use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Reporter\Traversal;
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
#[UsesClass(\App\Analyzer\Graph\NodeId\MethodNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\Node\ClassNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[UsesClass(Traversal\DepthFirstWalk::class)]
#[Small]
final class TraversalTest extends TestCase
{
    #[DataProvider('providerEveryTraversal')]
    public function testDirectionIsPartOfTheStrategyRatherThanOfEachCall(Traversal $traversal, Direction $direction): void
    {
        self::assertSame($direction, $traversal->direction());
        self::assertSame($direction, $traversal->direction());
    }

    #[DataProvider('providerEveryTraversal')]
    public function testTraverseHandsOverTheStartingSymbolFirst(Traversal $traversal, Direction $direction): void
    {
        $log = new VisitLog();
        $traversal->traverse(SampleGraph::invoice(), ClassNodeId::of('App\Domain\Invoice'), $log->recorder());

        self::assertSame('App\Domain\Invoice', $log->names()[0]);
        self::assertSame($direction, $traversal->direction());
    }

    #[DataProvider('providerEveryTraversal')]
    public function testTraverseFollowsOnlyTheRelationsOfItsOwnDirection(Traversal $traversal, Direction $direction): void
    {
        $log = new VisitLog();
        $traversal->traverse(SampleGraph::invoice(), ClassNodeId::of('App\Domain\Invoice'), $log->recorder());

        self::assertNotEmpty($log->names());
        self::assertSame(
            $log->names(),
            array_values(array_filter($log->names(), static fn (string $name): bool => SampleGraph::invoice()->nodeNamed($name) !== null)),
        );
        self::assertSame($direction, $traversal->direction());
    }

    #[DataProvider('providerEveryTraversal')]
    public function testTraverseStopsWhereTheVisitorSaysNotToDescend(Traversal $traversal, Direction $direction): void
    {
        $log = new VisitLog();
        $traversal->traverse(SampleGraph::invoice(), ClassNodeId::of('App\Domain\Invoice'), $log->recorderStoppingBelow(0));

        self::assertSame(['App\Domain\Invoice'], $log->names());
        self::assertSame($direction, $traversal->direction());
    }

    /**
     * @return iterable<string, array{Traversal, Direction}>
     */
    public static function providerEveryTraversal(): iterable
    {
        yield 'depth first, away from the subject' => [new DepthFirstTraversal(Direction::Uses), Direction::Uses];

        yield 'depth first, towards the subject' => [new DepthFirstTraversal(Direction::UsedBy), Direction::UsedBy];
    }
}
