<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\TreeReporter;

use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\Edge\Declaration\MethodEdge;
use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Graph;
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

/**
 * @internal
 */
#[CoversClass(TreeCursor::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(\App\Analyzer\Graph\EdgeKind::class)]
#[UsesClass(MethodEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Inverse\DeclaredInEdge::class)]
#[UsesClass(MethodCallEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Inverse\UsedByEdge::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(Graph::class)]
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
    #[DataProvider('providerInvoiceGraph')]
    public function testVisitWritesOneLineForTheSymbolItIsGiven(Graph $graph): void
    {
        $output = new BufferedOutput();
        $cursor = new TreeCursor($graph, new DepthFirstTraversal(Direction::Uses), new LineRenderer(), $output);

        $cursor->visit(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 0);

        self::assertSame("App\\Domain\\Invoice\n", $output->fetch());
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testVisitAsksTheTreeToContinueBelowAnExpandableSymbol(Graph $graph): void
    {
        $cursor = new TreeCursor($graph, new DepthFirstTraversal(Direction::Uses), new LineRenderer(), new BufferedOutput());

        self::assertTrue($cursor->visit(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 0));
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testVisitStopsTheTreeBelowASymbolAlreadyExpanded(Graph $graph): void
    {
        $cursor = new TreeCursor($graph, new DepthFirstTraversal(Direction::Uses), new LineRenderer(), new BufferedOutput());
        $node = new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true);

        $cursor->visit($node, 0);

        self::assertFalse($cursor->visit($node, 1));
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testVisitStopsTheTreeBelowTheBoundedLevel(Graph $graph): void
    {
        $cursor = new TreeCursor($graph, new DepthFirstTraversal(Direction::Uses), new LineRenderer(), new BufferedOutput(), 1);

        self::assertFalse($cursor->visit(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 2));
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testVisitWritesNothingBelowTheBoundedLevel(Graph $graph): void
    {
        $output = new BufferedOutput();
        $cursor = new TreeCursor($graph, new DepthFirstTraversal(Direction::Uses), new LineRenderer(), $output, 1);

        $cursor->visit(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 2);

        self::assertSame('', $output->fetch());
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testVisitWritesTheSymbolSittingExactlyAtTheBoundedLevel(Graph $graph): void
    {
        $output = new BufferedOutput();
        $cursor = new TreeCursor($graph, new DepthFirstTraversal(Direction::Uses), new LineRenderer(), $output, 1);

        $cursor->visit(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), 1);

        self::assertStringContainsString('App\Domain\Invoice::total', $output->fetch());
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testVisitBoundsNothingWhenNoLevelIsGiven(Graph $graph): void
    {
        $output = new BufferedOutput();
        $cursor = new TreeCursor($graph, new DepthFirstTraversal(Direction::Uses), new LineRenderer(), $output);

        $cursor->visit(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), 9);

        self::assertStringContainsString('App\Domain\Invoice::total', $output->fetch());
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testVisitMarksASymbolExpandedOnAnotherBranchRatherThanExpandingItAgain(Graph $graph): void
    {
        $output = new BufferedOutput();
        $cursor = new TreeCursor($graph, new DepthFirstTraversal(Direction::Uses), new LineRenderer(), $output);
        $shared = new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true);

        $cursor->visit(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 0);
        $cursor->visit($shared, 1);
        $cursor->visit(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), 1);
        $continues = $cursor->visit($shared, 2);

        self::assertFalse($continues);
        self::assertStringContainsString('App\Domain\Money::add (*)', $output->fetch());
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testVisitMarksASymbolThatClosesACycleOnTheCurrentPath(Graph $graph): void
    {
        $output = new BufferedOutput();
        $cursor = new TreeCursor($graph, new DepthFirstTraversal(Direction::Uses), new LineRenderer(), $output);
        $root = new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true);

        $cursor->visit($root, 0);
        $cursor->visit(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), 1);
        $continues = $cursor->visit($root, 2);

        self::assertFalse($continues);
        self::assertStringContainsString('App\Domain\Invoice (recursive)', $output->fetch());
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testVisitStopsTheTreeBelowASymbolOutsideTheAnalyzedSources(Graph $graph): void
    {
        $cursor = new TreeCursor($graph, new DepthFirstTraversal(Direction::Uses), new LineRenderer(), new BufferedOutput());

        self::assertFalse($cursor->visit(new BuiltinNode(BuiltinNodeId::of('int'), true), 0));
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testIsLastChildIsTrueForTheRootOfTheTree(Graph $graph): void
    {
        $cursor = new TreeCursor($graph, new DepthFirstTraversal(Direction::Uses), new LineRenderer(), new BufferedOutput());

        self::assertTrue($cursor->isLastChild(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 0));
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testIsLastChildIsTrueForTheLastSiblingDrawnUnderAParent(Graph $graph): void
    {
        $cursor = new TreeCursor($graph, new DepthFirstTraversal(Direction::Uses), new LineRenderer(), new BufferedOutput());
        $cursor->visit(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 0);

        self::assertTrue($cursor->isLastChild(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'lines'), true), 1));
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testIsLastChildIsFalseWhenAFurtherSiblingFollows(Graph $graph): void
    {
        $cursor = new TreeCursor($graph, new DepthFirstTraversal(Direction::Uses), new LineRenderer(), new BufferedOutput());
        $cursor->visit(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 0);

        self::assertFalse($cursor->isLastChild(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), 1));
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

    #[DataProvider('providerKindsWithNothingBelowThem')]
    public function testIsLeafKindRecognisesASymbolOutsideTheAnalyzedSources(NodeKind $kind): void
    {
        $cursor = new TreeCursor(new Graph(), new DepthFirstTraversal(Direction::Uses), new LineRenderer(), new BufferedOutput());

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
        $cursor = new TreeCursor(new Graph(), new DepthFirstTraversal(Direction::Uses), new LineRenderer(), new BufferedOutput());

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
