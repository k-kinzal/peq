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
use App\Reporter\Diagram\DiagramRenderer;
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
#[UsesClass(DiagramEdge::class)]
#[UsesClass(DiagramNode::class)]
#[UsesClass(DepthFirstTraversal::class)]
#[UsesClass(DepthFirstWalk::class)]
#[UsesClass(DiagramRenderer::class)]
#[UsesClass(GraphCursor::class)]
#[Small]
final class GraphReporterTest extends TestCase
{
    public function testReportDrawsTheWholeWalkAsNumberedSymbolsAndArrows(): void
    {
        $output = new BufferedOutput();
        (new GraphReporter(new DepthFirstTraversal(Direction::Uses)))
            ->report(SampleGraph::analysed(), ClassNodeId::of('App\Http\Controller'), $output)
        ;

        self::assertSame(
            <<<'DIAGRAM'
                (1) App\Http\Controller [class] /project/src/Http/Controller.php:10
                    ├── declaration-extends ──> (2) App\Http\Kernel
                    ├── declaration-method ──> (3) App\Http\Controller::show
                    └── declaration-method ──> (6) App\Http\Controller::store
                (2) App\Http\Kernel [class] /project/src/Http/Kernel.php:7
                (3) App\Http\Controller::show [method] /project/src/Http/Controller.php:20
                    └── method-call ──> (4) App\Domain\Invoice::total
                (4) App\Domain\Invoice::total [method] /project/src/Domain/Invoice.php:12
                    └── method-call ──> (5) App\Cache\Store::get
                (5) App\Cache\Store::get [method] /project/src/Cache/Store.php:8
                (6) App\Http\Controller::store [method] /project/src/Http/Controller.php:30
                    └── method-call ──> (4) App\Domain\Invoice::total

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

        self::assertStringNotContainsString('Invoice::total', $output->fetch());
    }

    public function testReportReadsTheGraphTheWayItsTraversalDoes(): void
    {
        $output = new BufferedOutput();
        (new GraphReporter(new DepthFirstTraversal(Direction::UsedBy)))
            ->report(SampleGraph::analysed(), MethodNodeId::of('App\Cache\Store', 'get'), $output)
        ;

        self::assertStringContainsString('used-by ──> (3) App\Domain\Invoice::total', $output->fetch());
    }

    public function testReportWritesNothingAtAllForASymbolTheGraphDoesNotHold(): void
    {
        $output = new BufferedOutput();
        (new GraphReporter(new DepthFirstTraversal(Direction::Uses)))
            ->report(SampleGraph::analysed(), ClassNodeId::of('App\Nothing'), $output)
        ;

        self::assertSame('', $output->fetch());
    }
}
