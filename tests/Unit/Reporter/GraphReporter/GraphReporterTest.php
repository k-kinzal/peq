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
use App\Reporter\Diagram\DiagramCanvas;
use App\Reporter\Diagram\DiagramEdge;
use App\Reporter\Diagram\DiagramNode;
use App\Reporter\Diagram\Layout\DiagramLayout;
use App\Reporter\Diagram\Layout\Lane;
use App\Reporter\Diagram\Layout\LaneRouting;
use App\Reporter\Diagram\Layout\LayeredLayout;
use App\Reporter\Diagram\Layout\LayerOrdering;
use App\Reporter\Diagram\Layout\LayoutItem;
use App\Reporter\Diagram\Layout\LayoutItemKind;
use App\Reporter\Diagram\Layout\RowPlacement;
use App\Reporter\Diagram\MermaidRenderer;
use App\Reporter\Diagram\TerminalRenderer;
use App\Reporter\Expansion;
use App\Reporter\GraphReporter\GraphCursor;
use App\Reporter\GraphReporter\GraphReporter;
use App\Reporter\Traversal\DepthFirstTraversal;
use App\Reporter\Traversal\DepthFirstWalk;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Fixture\Gql\SampleGraph;

/**
 * @internal
 */
#[UsesClass(\App\Analyzer\Graph\EdgeIdentity::class)]
#[CoversClass(GraphReporter::class)]
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
#[UsesClass(DiagramCanvas::class)]
#[UsesClass(DiagramEdge::class)]
#[UsesClass(DiagramNode::class)]
#[UsesClass(DiagramLayout::class)]
#[UsesClass(Lane::class)]
#[UsesClass(LaneRouting::class)]
#[UsesClass(LayeredLayout::class)]
#[UsesClass(LayerOrdering::class)]
#[UsesClass(LayoutItem::class)]
#[UsesClass(LayoutItemKind::class)]
#[UsesClass(RowPlacement::class)]
#[UsesClass(MermaidRenderer::class)]
#[UsesClass(TerminalRenderer::class)]
#[UsesClass(DepthFirstTraversal::class)]
#[UsesClass(DepthFirstWalk::class)]
#[UsesClass(GraphCursor::class)]
#[UsesClass(\App\Reporter\CallOccurrences::class)]
#[Small]
final class GraphReporterTest extends TestCase
{
    public function testReportDrawsTheWholeWalkLeftToRightEverySymbolOnce(): void
    {
        $output = new BufferedOutput();
        (new GraphReporter(new DepthFirstTraversal(Direction::Uses), null, new TerminalRenderer(120)))
            ->report(SampleGraph::analysed(), ClassNodeId::of('App\Http\Controller'), $output)
        ;

        self::assertSame(
            <<<'DIAGRAM'
                                      ┌──▶ App\Http\Kernel
                App\Http\Controller ──┼──▶ App\Http\Controller::show ───┐
                                      │                                 ├──▶ App\Domain\Invoice::total ──▶ App\Cache\Store::get
                                      └──▶ App\Http\Controller::store ──┘

                DIAGRAM,
            $output->fetch(),
        );
    }

    public function testReportCutsTheDrawingIntoBandsAsWideAsTheTerminalByDefault(): void
    {
        $output = new BufferedOutput();
        (new GraphReporter(new DepthFirstTraversal(Direction::Uses)))
            ->report(SampleGraph::analysed(), ClassNodeId::of('App\Http\Controller'), $output)
        ;

        self::assertSame(
            <<<'DIAGRAM'
                                      ┌──▶ App\Http\Kernel
                App\Http\Controller ──┼──▶ App\Http\Controller::show …
                                      └──▶ App\Http\Controller::store …

                App\Http\Controller::show ───┐
                                             ├──▶ App\Domain\Invoice::total …
                App\Http\Controller::store ──┘

                App\Domain\Invoice::total ──▶ App\Cache\Store::get

                DIAGRAM,
            $output->fetch(),
        );
    }

    public function testReportStopsAtTheLevelItIsBoundedAt(): void
    {
        $output = new BufferedOutput();
        (new GraphReporter(new DepthFirstTraversal(Direction::Uses), 1))
            ->report(SampleGraph::analysed(), ClassNodeId::of('App\Http\Controller'), $output)
        ;

        self::assertSame(
            <<<'DIAGRAM'
                                      ┌──▶ App\Http\Kernel
                App\Http\Controller ──┼──▶ App\Http\Controller::show
                                      └──▶ App\Http\Controller::store

                DIAGRAM,
            $output->fetch(),
        );
    }

    public function testReportReadsTheGraphTheWayItsTraversalDoes(): void
    {
        $output = new BufferedOutput();
        (new GraphReporter(new DepthFirstTraversal(Direction::UsedBy)))
            ->report(SampleGraph::analysed(), MethodNodeId::of('App\Cache\Store', 'get'), $output)
        ;

        self::assertSame(
            <<<'DIAGRAM'
                                       ┌──▶ App\Cache\Store
                App\Cache\Store::get ──┤
                                       └──▶ App\Domain\Invoice::total …

                                            ┌──▶ App\Domain\Invoice
                App\Domain\Invoice::total ──┼──▶ App\Http\Controller::show …
                                            └──▶ App\Http\Controller::store …

                App\Http\Controller::show ───┐
                                             ├──▶ App\Http\Controller
                App\Http\Controller::store ──┘

                DIAGRAM,
            $output->fetch(),
        );
    }

    public function testReportWritesTheWalkAsAMermaidFlowchartWhenGivenThatRenderer(): void
    {
        $output = new BufferedOutput();
        (new GraphReporter(new DepthFirstTraversal(Direction::Uses), null, new MermaidRenderer()))
            ->report(SampleGraph::analysed(), ClassNodeId::of('App\Http\Controller'), $output)
        ;

        self::assertSame(
            <<<'MERMAID'
                flowchart LR
                    n1["App\Http\Controller"]
                    n2["App\Http\Kernel"]
                    n3["App\Http\Controller::show"]
                    n4["App\Domain\Invoice::total"]
                    n5["App\Cache\Store::get"]
                    n6["App\Http\Controller::store"]
                    n1 -->|"declaration-extends"| n2
                    n1 -->|"declaration-method"| n3
                    n1 -->|"declaration-method"| n6
                    n3 -->|"method-call"| n4
                    n4 -->|"method-call"| n5
                    n6 -->|"method-call"| n4

                MERMAID,
            $output->fetch(),
        );
    }

    public function testReportWritesNothingAtAllForASymbolTheGraphDoesNotHold(): void
    {
        $output = new BufferedOutput();
        (new GraphReporter(new DepthFirstTraversal(Direction::Uses)))
            ->report(SampleGraph::analysed(), ClassNodeId::of('App\Nothing'), $output)
        ;

        self::assertSame('', $output->fetch());
    }

    public function testReportWritesNothingAtAllAsMermaidForASymbolTheGraphDoesNotHold(): void
    {
        $output = new BufferedOutput();
        (new GraphReporter(new DepthFirstTraversal(Direction::Uses), null, new MermaidRenderer()))
            ->report(SampleGraph::analysed(), ClassNodeId::of('App\Nothing'), $output)
        ;

        self::assertSame('', $output->fetch());
    }
}
