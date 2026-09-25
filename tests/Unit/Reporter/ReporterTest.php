<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter;

use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\Edge\Declaration\MethodEdge;
use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Reporter\Diagram\MermaidRenderer;
use App\Reporter\Diagram\TerminalRenderer;
use App\Reporter\DotReporter\DotReporter;
use App\Reporter\GraphReporter\GraphReporter;
use App\Reporter\JsonReporter\JsonReporter;
use App\Reporter\Reporter;
use App\Reporter\TableReporter\TableReporter;
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
#[UsesClass(\App\Reporter\RelationNotice::class)]
#[CoversClass(TreeReporter::class)]
#[CoversClass(JsonReporter::class)]
#[CoversClass(DotReporter::class)]
#[CoversClass(TableReporter::class)]
#[CoversClass(GraphReporter::class)]
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
#[UsesClass(\App\Reporter\DotReporter\DotCursor::class)]
#[UsesClass(\App\Reporter\DotReporter\StatementRenderer::class)]
#[UsesClass(\App\Reporter\JsonReporter\JsonCursor::class)]
#[UsesClass(\App\Reporter\TableReporter\TableCursor::class)]
#[UsesClass(\App\Reporter\GraphReporter\GraphCursor::class)]
#[UsesClass(\App\Reporter\Diagram\Diagram::class)]
#[UsesClass(\App\Reporter\Diagram\DiagramCanvas::class)]
#[UsesClass(\App\Reporter\Diagram\DiagramEdge::class)]
#[UsesClass(\App\Reporter\Diagram\DiagramNode::class)]
#[UsesClass(\App\Reporter\Diagram\Layout\DiagramLayout::class)]
#[UsesClass(\App\Reporter\Diagram\Layout\Lane::class)]
#[UsesClass(\App\Reporter\Diagram\Layout\LaneRouting::class)]
#[UsesClass(\App\Reporter\Diagram\Layout\LayeredLayout::class)]
#[UsesClass(\App\Reporter\Diagram\Layout\LayerOrdering::class)]
#[UsesClass(\App\Reporter\Diagram\Layout\LayoutItem::class)]
#[UsesClass(\App\Reporter\Diagram\Layout\LayoutItemKind::class)]
#[UsesClass(\App\Reporter\Diagram\Layout\RowPlacement::class)]
#[UsesClass(MermaidRenderer::class)]
#[UsesClass(TerminalRenderer::class)]
#[UsesClass(DepthFirstTraversal::class)]
#[UsesClass(\App\Reporter\Traversal\DepthFirstWalk::class)]
#[UsesClass(\App\Reporter\TreeReporter\LineRenderer::class)]
#[UsesClass(\App\Reporter\TreeReporter\TreeCursor::class)]
#[UsesClass(TreeReporterOptions::class)]
#[UsesClass(\App\Reporter\CallOccurrences::class)]
#[Small]
final class ReporterTest extends TestCase
{
    #[DataProvider('providerEveryReporter')]
    public function testReportWritesTheRequestedSymbolToTheGivenOutput(Reporter $reporter, string $spelling, Graph $graph): void
    {
        $output = new BufferedOutput();
        $reporter->report($graph, ClassNodeId::of('App\Domain\Invoice'), $output);

        self::assertStringContainsString($spelling, $output->fetch());
    }

    #[DataProvider('providerEveryReporter')]
    public function testReportWritesNowhereElseThanTheGivenOutput(Reporter $reporter, string $spelling, Graph $graph): void
    {
        $output = new BufferedOutput();
        $this->expectOutputString('');

        $reporter->report($graph, ClassNodeId::of('App\Domain\Invoice'), $output);

        self::assertStringContainsString($spelling, $output->fetch());
    }

    #[DataProvider('providerEveryReporter')]
    public function testReportWritesNothingForASymbolTheGraphDoesNotHold(Reporter $reporter, string $spelling, Graph $graph): void
    {
        $output = new BufferedOutput();
        $reporter->report($graph, ClassNodeId::of('App\Domain\Missing'), $output);

        self::assertSame('', $output->fetch());
        self::assertNotSame('', $spelling);
    }

    /**
     * @return iterable<string, array{Reporter, string, Graph}>
     */
    public static function providerEveryReporter(): iterable
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

        $plain = 'App\Domain\Invoice';
        $escaped = 'App\\\Domain\\\Invoice';

        yield 'the tree reporter reading away from the subject' => [
            new TreeReporter(new TreeReporterOptions(), new DepthFirstTraversal(Direction::Uses)),
            $plain,
            $graph,
        ];

        yield 'the tree reporter reading towards the subject' => [
            new TreeReporter(new TreeReporterOptions(), new DepthFirstTraversal(Direction::UsedBy)),
            $plain,
            $graph,
        ];

        yield 'the JSON reporter reading away from the subject' => [
            new JsonReporter(new DepthFirstTraversal(Direction::Uses)),
            $escaped,
            $graph,
        ];

        yield 'the JSON reporter reading towards the subject' => [
            new JsonReporter(new DepthFirstTraversal(Direction::UsedBy)),
            $escaped,
            $graph,
        ];

        yield 'the digraph reporter reading away from the subject' => [
            new DotReporter(new DepthFirstTraversal(Direction::Uses)),
            $escaped,
            $graph,
        ];

        yield 'the digraph reporter reading towards the subject' => [
            new DotReporter(new DepthFirstTraversal(Direction::UsedBy)),
            $escaped,
            $graph,
        ];

        yield 'the table reporter reading away from the subject' => [
            new TableReporter(new DepthFirstTraversal(Direction::Uses)),
            $plain,
            $graph,
        ];

        yield 'the table reporter reading towards the subject' => [
            new TableReporter(new DepthFirstTraversal(Direction::UsedBy)),
            $plain,
            $graph,
        ];

        yield 'the graph reporter drawing in the terminal, reading away from the subject' => [
            new GraphReporter(new DepthFirstTraversal(Direction::Uses), null, new TerminalRenderer(80)),
            $plain,
            $graph,
        ];

        yield 'the graph reporter drawing in the terminal, reading towards the subject' => [
            new GraphReporter(new DepthFirstTraversal(Direction::UsedBy), null, new TerminalRenderer(80)),
            $plain,
            $graph,
        ];

        yield 'the graph reporter writing Mermaid, reading away from the subject' => [
            new GraphReporter(new DepthFirstTraversal(Direction::Uses), null, new MermaidRenderer()),
            $plain,
            $graph,
        ];

        yield 'the graph reporter writing Mermaid, reading towards the subject' => [
            new GraphReporter(new DepthFirstTraversal(Direction::UsedBy), null, new MermaidRenderer()),
            $plain,
            $graph,
        ];
    }
}
