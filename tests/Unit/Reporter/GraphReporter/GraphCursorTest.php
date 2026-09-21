<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\GraphReporter;

use App\Analyzer\Graph\AuthoredEdge;
use App\Analyzer\Graph\Declaration\AttributeUsage;
use App\Analyzer\Graph\Declaration\Modifiers;
use App\Analyzer\Graph\Declaration\Parameter;
use App\Analyzer\Graph\Declaration\Signature;
use App\Analyzer\Graph\Declaration\SymbolDeclaration;
use App\Analyzer\Graph\Declaration\Visibility;
use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\Edge\Declaration\ExtendsEdge;
use App\Analyzer\Graph\Edge\Declaration\MethodEdge;
use App\Analyzer\Graph\Edge\Inverse\DeclaredInEdge;
use App\Analyzer\Graph\Edge\Inverse\UsedByEdge;
use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\UnknownNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeId\UnknownNodeId;
use App\Analyzer\Graph\NodeKind;
use App\Analyzer\Graph\NodePrecedence;
use App\Analyzer\Graph\QualifiedName;
use App\Reporter\Continuation;
use App\Reporter\Diagram\Diagram;
use App\Reporter\Diagram\DiagramEdge;
use App\Reporter\Diagram\DiagramNode;
use App\Reporter\Expansion;
use App\Reporter\GraphReporter\GraphCursor;
use App\Reporter\Traversal\DepthFirstTraversal;
use App\Reporter\Traversal\DepthFirstWalk;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Gql\SampleGraph;

/**
 * @internal
 */
#[CoversClass(GraphCursor::class)]
#[UsesClass(AuthoredEdge::class)]
#[UsesClass(AttributeUsage::class)]
#[UsesClass(Modifiers::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(Signature::class)]
#[UsesClass(SymbolDeclaration::class)]
#[UsesClass(Visibility::class)]
#[UsesClass(Direction::class)]
#[UsesClass(ExtendsEdge::class)]
#[UsesClass(MethodEdge::class)]
#[UsesClass(DeclaredInEdge::class)]
#[UsesClass(UsedByEdge::class)]
#[UsesClass(MethodCallEdge::class)]
#[UsesClass(EdgeKind::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(Graph::class)]
#[UsesClass(ClassNode::class)]
#[UsesClass(MethodNode::class)]
#[UsesClass(UnknownNode::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(UnknownNodeId::class)]
#[UsesClass(NodeKind::class)]
#[UsesClass(NodePrecedence::class)]
#[UsesClass(QualifiedName::class)]
#[UsesClass(Continuation::class)]
#[UsesClass(Expansion::class)]
#[UsesClass(Diagram::class)]
#[UsesClass(DiagramEdge::class)]
#[UsesClass(DiagramNode::class)]
#[UsesClass(DepthFirstTraversal::class)]
#[UsesClass(DepthFirstWalk::class)]
#[Small]
final class GraphCursorTest extends TestCase
{
    public function testVisitRecordsTheSymbolsTheWalkReached(): void
    {
        $cursor = new GraphCursor(SampleGraph::analysed(), new DepthFirstTraversal(Direction::Uses));
        (new DepthFirstTraversal(Direction::Uses))->traverse(
            SampleGraph::analysed(),
            ClassNodeId::of('App\Http\Controller'),
            $cursor->visit(...),
        );

        self::assertCount(6, $cursor->diagram()->nodes());
    }

    public function testVisitStopsAtTheLevelItIsBoundedAt(): void
    {
        $cursor = new GraphCursor(SampleGraph::analysed(), new DepthFirstTraversal(Direction::Uses), 1);
        (new DepthFirstTraversal(Direction::Uses))->traverse(
            SampleGraph::analysed(),
            ClassNodeId::of('App\Http\Controller'),
            $cursor->visit(...),
        );

        self::assertNull($cursor->diagram()->numberOf('App\Domain\Invoice::total'));
    }

    public function testDiagramDrawsASymbolSeveralBranchesReachOnlyOnce(): void
    {
        $cursor = new GraphCursor(SampleGraph::analysed(), new DepthFirstTraversal(Direction::Uses));
        (new DepthFirstTraversal(Direction::Uses))->traverse(
            SampleGraph::analysed(),
            ClassNodeId::of('App\Http\Controller'),
            $cursor->visit(...),
        );

        self::assertSame(4, $cursor->diagram()->numberOf('App\Domain\Invoice::total'));
    }

    public function testDiagramDrawsOnlyTheRelationsItsTraversalFollows(): void
    {
        $cursor = new GraphCursor(SampleGraph::analysed(), new DepthFirstTraversal(Direction::Uses));
        (new DepthFirstTraversal(Direction::Uses))->traverse(
            SampleGraph::analysed(),
            ClassNodeId::of('App\Http\Controller'),
            $cursor->visit(...),
        );
        $labels = array_map(static fn (DiagramEdge $edge): string => $edge->label, $cursor->diagram()->edges());

        self::assertNotContains('used-by', $labels);
    }

    public function testDiagramDescribesEachSymbolByWhatItIsAndWhereItIsWritten(): void
    {
        $cursor = new GraphCursor(SampleGraph::analysed(), new DepthFirstTraversal(Direction::Uses), 1);
        (new DepthFirstTraversal(Direction::Uses))->traverse(
            SampleGraph::analysed(),
            ClassNodeId::of('App\Http\Controller'),
            $cursor->visit(...),
        );

        self::assertEquals(
            [
                new DiagramNode('App\Http\Controller', 'class', '/project/src/Http/Controller.php:10'),
                new DiagramNode('App\Http\Kernel', 'class', '/project/src/Http/Kernel.php:7'),
                new DiagramNode('App\Http\Controller::show', 'method', '/project/src/Http/Controller.php:20'),
                new DiagramNode('App\Http\Controller::store', 'method', '/project/src/Http/Controller.php:30'),
            ],
            $cursor->diagram()->nodes(),
        );
    }

    public function testDiagramNamesEachRelationByWhatItIsAndLeavesOutOnesOutOfReach(): void
    {
        $cursor = new GraphCursor(SampleGraph::analysed(), new DepthFirstTraversal(Direction::Uses), 1);
        (new DepthFirstTraversal(Direction::Uses))->traverse(
            SampleGraph::analysed(),
            ClassNodeId::of('App\Http\Controller'),
            $cursor->visit(...),
        );

        self::assertEquals(
            [
                new DiagramEdge('App\Http\Controller', 'App\Http\Kernel', 'declaration-extends'),
                new DiagramEdge('App\Http\Controller', 'App\Http\Controller::show', 'declaration-method'),
                new DiagramEdge('App\Http\Controller', 'App\Http\Controller::store', 'declaration-method'),
            ],
            $cursor->diagram()->edges(),
        );
    }

    public function testDiagramDoesNotSayWhereASymbolAnalysisOnlySawReferredToIsWritten(): void
    {
        $cursor = new GraphCursor(SampleGraph::analysed(), new DepthFirstTraversal(Direction::Uses));
        (new DepthFirstTraversal(Direction::Uses))->traverse(
            SampleGraph::analysed(),
            new UnknownNodeId('App\Missing'),
            $cursor->visit(...),
        );

        self::assertEquals([new DiagramNode('App\Missing', 'unknown')], $cursor->diagram()->nodes());
    }

    public function testDiagramDrawsNothingForASymbolTheGraphDoesNotHold(): void
    {
        $cursor = new GraphCursor(SampleGraph::analysed(), new DepthFirstTraversal(Direction::Uses));
        (new DepthFirstTraversal(Direction::Uses))->traverse(
            SampleGraph::analysed(),
            ClassNodeId::of('App\Nothing'),
            $cursor->visit(...),
        );

        self::assertTrue($cursor->diagram()->empty());
    }
}
