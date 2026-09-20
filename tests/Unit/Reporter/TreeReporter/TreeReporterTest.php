<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\TreeReporter;

use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\Edge\Declaration\MethodEdge;
use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Reporter\Traversal\DepthFirstTraversal;
use App\Reporter\TreeReporter\TreeReporter;
use App\Reporter\TreeReporter\TreeReporterOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @internal
 */
#[CoversClass(TreeReporter::class)]
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
#[UsesClass(ClassNode::class)]
#[UsesClass(MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[UsesClass(\App\Reporter\Continuation::class)]
#[UsesClass(\App\Reporter\Expansion::class)]
#[UsesClass(DepthFirstTraversal::class)]
#[UsesClass(\App\Reporter\Traversal\DepthFirstWalk::class)]
#[UsesClass(\App\Reporter\TreeReporter\LineRenderer::class)]
#[UsesClass(\App\Reporter\TreeReporter\TreeCursor::class)]
#[UsesClass(TreeReporterOptions::class)]
#[Small]
final class TreeReporterTest extends TestCase
{
    #[DataProvider('providerInvoiceGraph')]
    public function testReportWritesTheRootSymbolWithoutAnyPrefix(Graph $graph): void
    {
        $output = new BufferedOutput();
        (new TreeReporter(new TreeReporterOptions(), new DepthFirstTraversal(Direction::Uses)))
            ->report($graph, ClassNodeId::of('App\Domain\Invoice'), $output)
        ;

        self::assertStringStartsWith("App\\Domain\\Invoice\n", $output->fetch());
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testReportDrawsTheWholeTreeBelowTheRoot(Graph $graph): void
    {
        $output = new BufferedOutput();
        (new TreeReporter(new TreeReporterOptions(), new DepthFirstTraversal(Direction::Uses)))
            ->report($graph, ClassNodeId::of('App\Domain\Invoice'), $output)
        ;

        self::assertSame(
            "App\\Domain\\Invoice\n"
            ."├── App\\Domain\\Invoice::total\n"
            ."│   └── App\\Domain\\Money::add\n"
            ."└── App\\Domain\\Invoice::lines\n",
            $output->fetch(),
        );
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testReportStopsAtTheLevelTheConfigurationBounds(Graph $graph): void
    {
        $output = new BufferedOutput();
        (new TreeReporter(new TreeReporterOptions(level: 1), new DepthFirstTraversal(Direction::Uses)))
            ->report($graph, ClassNodeId::of('App\Domain\Invoice'), $output)
        ;

        self::assertSame(
            "App\\Domain\\Invoice\n"
            ."├── App\\Domain\\Invoice::total\n"
            ."└── App\\Domain\\Invoice::lines\n",
            $output->fetch(),
        );
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testReportReadsTheGraphTheWayItsTraversalDoes(Graph $graph): void
    {
        $output = new BufferedOutput();
        (new TreeReporter(new TreeReporterOptions(), new DepthFirstTraversal(Direction::UsedBy)))
            ->report($graph, MethodNodeId::of('App\Domain\Money', 'add'), $output)
        ;

        self::assertSame(
            "App\\Domain\\Money::add\n"
            ."└── App\\Domain\\Invoice::total\n"
            ."    └── App\\Domain\\Invoice\n",
            $output->fetch(),
        );
    }

    #[DataProvider('providerCyclicGraph')]
    public function testReportMarksASymbolThatClosesACycle(Graph $graph): void
    {
        $output = new BufferedOutput();
        (new TreeReporter(new TreeReporterOptions(), new DepthFirstTraversal(Direction::Uses)))
            ->report($graph, MethodNodeId::of('App\Domain\Invoice', 'total'), $output)
        ;

        self::assertStringContainsString('(recursive)', $output->fetch());
    }

    /**
     * @return iterable<string, array{Graph}>
     */
    public static function providerCyclicGraph(): iterable
    {
        $meta = new FileMeta('/project/src/Domain/Invoice.php', 12, 1);
        $total = new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true, $meta);
        $add = new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true, $meta);
        $graph = new Graph();
        $graph->addNodes([$total, $add]);
        $graph->addEdges([
            new MethodCallEdge($total, $add, $meta),
            new MethodCallEdge($add, $total, $meta),
        ]);

        yield 'App\Domain\Invoice::total and App\Domain\Money::add call each other' => [$graph];
    }

    #[DataProvider('providerSharedDependencyGraph')]
    public function testReportMarksASymbolItAlreadyExpandedElsewhere(Graph $graph): void
    {
        $output = new BufferedOutput();
        (new TreeReporter(new TreeReporterOptions(), new DepthFirstTraversal(Direction::Uses)))
            ->report($graph, ClassNodeId::of('App\Domain\Invoice'), $output)
        ;

        self::assertStringContainsString('(*)', $output->fetch());
    }

    /**
     * @return iterable<string, array{Graph}>
     */
    public static function providerSharedDependencyGraph(): iterable
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
            new MethodCallEdge($lines, $add, $meta),
        ]);

        yield 'App\Domain\Invoice::total and lines both call App\Domain\Money::add' => [$graph];
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testReportWritesNothingForASymbolTheGraphDoesNotHold(Graph $graph): void
    {
        $output = new BufferedOutput();
        (new TreeReporter(new TreeReporterOptions(), new DepthFirstTraversal(Direction::Uses)))
            ->report($graph, ClassNodeId::of('App\Domain\Missing'), $output)
        ;

        self::assertSame('', $output->fetch());
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testReportCanBeRunTwiceWithTheSameReporter(Graph $graph): void
    {
        $reporter = new TreeReporter(new TreeReporterOptions(), new DepthFirstTraversal(Direction::Uses));

        $first = new BufferedOutput();
        $reporter->report($graph, ClassNodeId::of('App\Domain\Invoice'), $first);
        $second = new BufferedOutput();
        $reporter->report($graph, ClassNodeId::of('App\Domain\Invoice'), $second);

        self::assertSame($first->fetch(), $second->fetch());
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
}
