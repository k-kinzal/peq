<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\TableReporter;

use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\Edge\Declaration\MethodEdge;
use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Reporter\TableReporter\TableReporter;
use App\Reporter\Traversal\DepthFirstTraversal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @internal
 */
#[CoversClass(TableReporter::class)]
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
#[UsesClass(\App\Reporter\TableReporter\TableCursor::class)]
#[UsesClass(DepthFirstTraversal::class)]
#[UsesClass(\App\Reporter\Traversal\DepthFirstWalk::class)]
#[Small]
final class TableReporterTest extends TestCase
{
    #[DataProvider('providerInvoiceGraph')]
    public function testReportWritesOneRowPerSymbolTheWalkReached(Graph $graph): void
    {
        $output = new BufferedOutput();
        (new TableReporter(new DepthFirstTraversal(Direction::Uses)))
            ->report($graph, ClassNodeId::of('App\Domain\Invoice'), $output)
        ;

        self::assertSame(
            <<<'TABLE'
                +-------+---------------------------+--------+------------------------------------+
                | Depth | Symbol                    | Kind   | Location                           |
                +-------+---------------------------+--------+------------------------------------+
                | 0     | App\Domain\Invoice        | class  | /project/src/Domain/Invoice.php:12 |
                | 1     | App\Domain\Invoice::total | method | /project/src/Domain/Invoice.php:12 |
                | 2     | App\Domain\Money::add     | method | /project/src/Domain/Invoice.php:12 |
                | 1     | App\Domain\Invoice::lines | method | /project/src/Domain/Invoice.php:12 |
                +-------+---------------------------+--------+------------------------------------+

                TABLE,
            $output->fetch(),
        );
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testReportStopsAtTheLevelItIsBoundedAt(Graph $graph): void
    {
        $output = new BufferedOutput();
        (new TableReporter(new DepthFirstTraversal(Direction::Uses), 1))
            ->report($graph, ClassNodeId::of('App\Domain\Invoice'), $output)
        ;

        self::assertStringNotContainsString('App\Domain\Money::add', $output->fetch());
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testReportReadsTheGraphTheWayItsTraversalDoes(Graph $graph): void
    {
        $output = new BufferedOutput();
        (new TableReporter(new DepthFirstTraversal(Direction::UsedBy)))
            ->report($graph, MethodNodeId::of('App\Domain\Money', 'add'), $output)
        ;

        self::assertStringContainsString('App\Domain\Invoice::total', $output->fetch());
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testReportWritesNothingAtAllForASymbolTheGraphDoesNotHold(Graph $graph): void
    {
        $output = new BufferedOutput();
        (new TableReporter(new DepthFirstTraversal(Direction::Uses)))
            ->report($graph, ClassNodeId::of('App\Domain\Missing'), $output)
        ;

        self::assertSame('', $output->fetch());
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
