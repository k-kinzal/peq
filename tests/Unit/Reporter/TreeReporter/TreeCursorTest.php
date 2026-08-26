<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\TreeReporter;

use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\Node\BuiltinNode;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\BuiltinNodeId;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeKind;
use App\Reporter\Traversal\DepthFirstTraversal;
use App\Reporter\TreeReporter\LineRenderer;
use App\Reporter\TreeReporter\TreeCursor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Fixture\Graph\SampleGraph;

/**
 * @internal
 */
#[CoversClass(TreeCursor::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(\App\Analyzer\Graph\EdgeKind::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Declaration\MethodEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Inverse\DeclaredInEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Usage\MethodCallEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Inverse\UsedByEdge::class)]
#[UsesClass(\App\Analyzer\Graph\FileMeta::class)]
#[UsesClass(\App\Analyzer\Graph\Graph::class)]
#[UsesClass(BuiltinNodeId::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(BuiltinNode::class)]
#[UsesClass(ClassNode::class)]
#[UsesClass(MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[UsesClass(DepthFirstTraversal::class)]
#[UsesClass(LineRenderer::class)]
#[Small]
final class TreeCursorTest extends TestCase
{
    public function testVisitWritesOneLineForTheSymbolItIsGiven(): void
    {
        $output = new BufferedOutput();
        $cursor = new TreeCursor(SampleGraph::invoice(), new DepthFirstTraversal(Direction::Uses), new LineRenderer(), $output);

        $cursor->visit(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 0);

        self::assertSame("App\\Domain\\Invoice\n", $output->fetch());
    }

    public function testVisitAsksTheTreeToContinueBelowAnExpandableSymbol(): void
    {
        $cursor = new TreeCursor(SampleGraph::invoice(), new DepthFirstTraversal(Direction::Uses), new LineRenderer(), new BufferedOutput());

        self::assertTrue($cursor->visit(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 0));
    }

    public function testVisitStopsTheTreeBelowASymbolAlreadyExpanded(): void
    {
        $cursor = new TreeCursor(SampleGraph::invoice(), new DepthFirstTraversal(Direction::Uses), new LineRenderer(), new BufferedOutput());
        $node = new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true);

        $cursor->visit($node, 0);

        self::assertFalse($cursor->visit($node, 1));
    }

    public function testVisitStopsTheTreeBelowTheBoundedLevel(): void
    {
        $cursor = new TreeCursor(SampleGraph::invoice(), new DepthFirstTraversal(Direction::Uses), new LineRenderer(), new BufferedOutput(), 1);

        self::assertFalse($cursor->visit(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 2));
    }

    public function testVisitWritesNothingBelowTheBoundedLevel(): void
    {
        $output = new BufferedOutput();
        $cursor = new TreeCursor(SampleGraph::invoice(), new DepthFirstTraversal(Direction::Uses), new LineRenderer(), $output, 1);

        $cursor->visit(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 2);

        self::assertSame('', $output->fetch());
    }

    public function testVisitWritesTheSymbolSittingExactlyAtTheBoundedLevel(): void
    {
        $output = new BufferedOutput();
        $cursor = new TreeCursor(SampleGraph::invoice(), new DepthFirstTraversal(Direction::Uses), new LineRenderer(), $output, 1);

        $cursor->visit(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), 1);

        self::assertStringContainsString('App\Domain\Invoice::total', $output->fetch());
    }

    public function testVisitBoundsNothingWhenNoLevelIsGiven(): void
    {
        $output = new BufferedOutput();
        $cursor = new TreeCursor(SampleGraph::invoice(), new DepthFirstTraversal(Direction::Uses), new LineRenderer(), $output);

        $cursor->visit(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), 9);

        self::assertStringContainsString('App\Domain\Invoice::total', $output->fetch());
    }

    public function testVisitMarksASymbolExpandedOnAnotherBranchRatherThanExpandingItAgain(): void
    {
        $output = new BufferedOutput();
        $cursor = new TreeCursor(SampleGraph::invoice(), new DepthFirstTraversal(Direction::Uses), new LineRenderer(), $output);
        $shared = new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true);

        $cursor->visit(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 0);
        $cursor->visit($shared, 1);
        $cursor->visit(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), 1);
        $continues = $cursor->visit($shared, 2);

        self::assertFalse($continues);
        self::assertStringContainsString('App\Domain\Money::add (*)', $output->fetch());
    }

    public function testVisitMarksASymbolThatClosesACycleOnTheCurrentPath(): void
    {
        $output = new BufferedOutput();
        $cursor = new TreeCursor(SampleGraph::invoice(), new DepthFirstTraversal(Direction::Uses), new LineRenderer(), $output);
        $root = new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true);

        $cursor->visit($root, 0);
        $cursor->visit(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), 1);
        $continues = $cursor->visit($root, 2);

        self::assertFalse($continues);
        self::assertStringContainsString('App\Domain\Invoice (recursive)', $output->fetch());
    }

    public function testVisitStopsTheTreeBelowASymbolOutsideTheAnalyzedSources(): void
    {
        $cursor = new TreeCursor(SampleGraph::invoice(), new DepthFirstTraversal(Direction::Uses), new LineRenderer(), new BufferedOutput());

        self::assertFalse($cursor->visit(new BuiltinNode(BuiltinNodeId::of('int'), true), 0));
    }

    public function testIsLastChildIsTrueForTheRootOfTheTree(): void
    {
        $cursor = new TreeCursor(SampleGraph::invoice(), new DepthFirstTraversal(Direction::Uses), new LineRenderer(), new BufferedOutput());

        self::assertTrue($cursor->isLastChild(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 0));
    }

    public function testIsLastChildIsTrueForTheLastSiblingDrawnUnderAParent(): void
    {
        $cursor = new TreeCursor(SampleGraph::invoice(), new DepthFirstTraversal(Direction::Uses), new LineRenderer(), new BufferedOutput());
        $cursor->visit(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 0);

        self::assertTrue($cursor->isLastChild(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'lines'), true), 1));
    }

    public function testIsLastChildIsFalseWhenAFurtherSiblingFollows(): void
    {
        $cursor = new TreeCursor(SampleGraph::invoice(), new DepthFirstTraversal(Direction::Uses), new LineRenderer(), new BufferedOutput());
        $cursor->visit(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 0);

        self::assertFalse($cursor->isLastChild(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), 1));
    }

    #[DataProvider('providerKindsWithNothingBelowThem')]
    public function testIsLeafKindRecognisesASymbolOutsideTheAnalyzedSources(NodeKind $kind): void
    {
        $cursor = new TreeCursor(SampleGraph::invoice(), new DepthFirstTraversal(Direction::Uses), new LineRenderer(), new BufferedOutput());

        self::assertTrue($cursor->isLeafKind($kind));
    }

    /**
     * @return iterable<string, array{NodeKind}>
     */
    public static function providerKindsWithNothingBelowThem(): iterable
    {
        yield 'a builtin type' => [NodeKind::Builtin];

        yield 'an unresolved symbol' => [NodeKind::Unknown];
    }

    #[DataProvider('providerKindsThatCanHaveRelations')]
    public function testIsLeafKindRecognisesASymbolThatCanRelateToOthers(NodeKind $kind): void
    {
        $cursor = new TreeCursor(SampleGraph::invoice(), new DepthFirstTraversal(Direction::Uses), new LineRenderer(), new BufferedOutput());

        self::assertFalse($cursor->isLeafKind($kind));
    }

    /**
     * @return iterable<string, array{NodeKind}>
     */
    public static function providerKindsThatCanHaveRelations(): iterable
    {
        foreach (NodeKind::cases() as $kind) {
            if ($kind !== NodeKind::Builtin && $kind !== NodeKind::Unknown) {
                yield $kind->value => [$kind];
            }
        }
    }
}
