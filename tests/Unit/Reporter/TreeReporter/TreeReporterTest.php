<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\TreeReporter;

use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Reporter\Traversal\DepthFirstTraversal;
use App\Reporter\TreeReporter\TreeReporter;
use App\Reporter\TreeReporter\TreeReporterOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Fixture\Graph\SampleGraph;

/**
 * @internal
 */
#[CoversClass(TreeReporter::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(\App\Analyzer\Graph\EdgeKind::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Declaration\MethodEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Inverse\DeclaredInEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Usage\MethodCallEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Inverse\UsedByEdge::class)]
#[UsesClass(\App\Analyzer\Graph\FileMeta::class)]
#[UsesClass(\App\Analyzer\Graph\Graph::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\Node\ClassNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[UsesClass(DepthFirstTraversal::class)]
#[UsesClass(\App\Reporter\Traversal\DepthFirstWalk::class)]
#[UsesClass(\App\Reporter\TreeReporter\LineRenderer::class)]
#[UsesClass(\App\Reporter\TreeReporter\TreeCursor::class)]
#[UsesClass(TreeReporterOptions::class)]
#[Small]
final class TreeReporterTest extends TestCase
{
    public function testReportWritesTheRootSymbolWithoutAnyPrefix(): void
    {
        $output = new BufferedOutput();
        (new TreeReporter(new TreeReporterOptions(), new DepthFirstTraversal(Direction::Uses)))
            ->report(SampleGraph::invoice(), ClassNodeId::of('App\Domain\Invoice'), $output)
        ;

        self::assertStringStartsWith("App\\Domain\\Invoice\n", $output->fetch());
    }

    public function testReportDrawsTheWholeTreeBelowTheRoot(): void
    {
        $output = new BufferedOutput();
        (new TreeReporter(new TreeReporterOptions(), new DepthFirstTraversal(Direction::Uses)))
            ->report(SampleGraph::invoice(), ClassNodeId::of('App\Domain\Invoice'), $output)
        ;

        self::assertSame(
            "App\\Domain\\Invoice\n"
            ."├── App\\Domain\\Invoice::total\n"
            ."│   └── App\\Domain\\Money::add\n"
            ."└── App\\Domain\\Invoice::lines\n",
            $output->fetch(),
        );
    }

    public function testReportStopsAtTheLevelTheConfigurationBounds(): void
    {
        $output = new BufferedOutput();
        (new TreeReporter(new TreeReporterOptions(level: 1), new DepthFirstTraversal(Direction::Uses)))
            ->report(SampleGraph::invoice(), ClassNodeId::of('App\Domain\Invoice'), $output)
        ;

        self::assertSame(
            "App\\Domain\\Invoice\n"
            ."├── App\\Domain\\Invoice::total\n"
            ."└── App\\Domain\\Invoice::lines\n",
            $output->fetch(),
        );
    }

    public function testReportReadsTheGraphTheWayItsTraversalDoes(): void
    {
        $output = new BufferedOutput();
        (new TreeReporter(new TreeReporterOptions(), new DepthFirstTraversal(Direction::UsedBy)))
            ->report(SampleGraph::invoice(), MethodNodeId::of('App\Domain\Money', 'add'), $output)
        ;

        self::assertSame(
            "App\\Domain\\Money::add\n"
            ."└── App\\Domain\\Invoice::total\n"
            ."    └── App\\Domain\\Invoice\n",
            $output->fetch(),
        );
    }

    public function testReportMarksASymbolThatClosesACycle(): void
    {
        $output = new BufferedOutput();
        (new TreeReporter(new TreeReporterOptions(), new DepthFirstTraversal(Direction::Uses)))
            ->report(SampleGraph::cyclic(), MethodNodeId::of('App\Domain\Invoice', 'total'), $output)
        ;

        self::assertStringContainsString('(recursive)', $output->fetch());
    }

    public function testReportMarksASymbolItAlreadyExpandedElsewhere(): void
    {
        $output = new BufferedOutput();
        (new TreeReporter(new TreeReporterOptions(), new DepthFirstTraversal(Direction::Uses)))
            ->report(SampleGraph::sharedDependency(), ClassNodeId::of('App\Domain\Invoice'), $output)
        ;

        self::assertStringContainsString('(*)', $output->fetch());
    }

    public function testReportWritesNothingForASymbolTheGraphDoesNotHold(): void
    {
        $output = new BufferedOutput();
        (new TreeReporter(new TreeReporterOptions(), new DepthFirstTraversal(Direction::Uses)))
            ->report(SampleGraph::invoice(), ClassNodeId::of('App\Domain\Missing'), $output)
        ;

        self::assertSame('', $output->fetch());
    }

    public function testReportCanBeRunTwiceWithTheSameReporter(): void
    {
        $reporter = new TreeReporter(new TreeReporterOptions(), new DepthFirstTraversal(Direction::Uses));

        $first = new BufferedOutput();
        $reporter->report(SampleGraph::invoice(), ClassNodeId::of('App\Domain\Invoice'), $first);
        $second = new BufferedOutput();
        $reporter->report(SampleGraph::invoice(), ClassNodeId::of('App\Domain\Invoice'), $second);

        self::assertSame($first->fetch(), $second->fetch());
    }
}
