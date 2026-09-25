<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\JsonReporter;

use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\Edge\Declaration\MethodEdge;
use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Reporter\JsonReporter\JsonReporter;
use App\Reporter\Traversal\DepthFirstTraversal;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @internal
 */
#[CoversClass(JsonReporter::class)]
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
#[UsesClass(\App\Reporter\JsonReporter\JsonCursor::class)]
#[UsesClass(DepthFirstTraversal::class)]
#[UsesClass(\App\Reporter\Traversal\DepthFirstWalk::class)]
#[UsesClass(\App\Reporter\CallOccurrences::class)]
#[Small]
final class JsonReporterTest extends TestCase
{
    /**
     * @throws JsonException
     */
    #[DataProvider('providerInvoiceGraph')]
    public function testReportWritesTheWholeWalkAsOneDocument(Graph $graph): void
    {
        $output = new BufferedOutput();
        (new JsonReporter(new DepthFirstTraversal(Direction::Uses)))
            ->report($graph, ClassNodeId::of('App\Domain\Invoice'), $output)
        ;

        self::assertJsonStringEqualsJsonString(
            <<<'JSON'
                {
                    "direction": "uses",
                    "symbol": "App\\Domain\\Invoice",
                    "nodes": [
                        {
                            "id": "App\\Domain\\Invoice",
                            "kind": "class",
                            "resolved": true,
                            "depth": 0,
                            "parent": null,
                            "relations": [],
                            "truncated": null,
                            "file": {"path": "/project/src/Domain/Invoice.php", "line": 12, "column": 1}
                        },
                        {
                            "id": "App\\Domain\\Invoice::total",
                            "kind": "method",
                            "resolved": true,
                            "depth": 1,
                            "parent": "App\\Domain\\Invoice",
                            "relations": ["declaration-method"],
                            "truncated": null,
                            "file": {"path": "/project/src/Domain/Invoice.php", "line": 12, "column": 1}
                        },
                        {
                            "id": "App\\Domain\\Money::add",
                            "kind": "method",
                            "resolved": true,
                            "depth": 2,
                            "parent": "App\\Domain\\Invoice::total",
                            "relations": ["method-call"],
                            "truncated": null,
                            "file": {"path": "/project/src/Domain/Invoice.php", "line": 12, "column": 1}
                        },
                        {
                            "id": "App\\Domain\\Invoice::lines",
                            "kind": "method",
                            "resolved": true,
                            "depth": 1,
                            "parent": "App\\Domain\\Invoice",
                            "relations": ["declaration-method"],
                            "truncated": null,
                            "file": {"path": "/project/src/Domain/Invoice.php", "line": 12, "column": 1}
                        }
                    ]
                }
                JSON,
            $output->fetch(),
        );
    }

    /**
     * @throws JsonException
     */
    #[DataProvider('providerInvoiceGraph')]
    public function testReportWritesADocumentAPersonCanReadAsWell(Graph $graph): void
    {
        $output = new BufferedOutput();
        (new JsonReporter(new DepthFirstTraversal(Direction::Uses)))
            ->report($graph, ClassNodeId::of('App\Domain\Invoice'), $output)
        ;

        self::assertStringStartsWith("{\n    \"direction\": \"uses\",", $output->fetch());
    }

    /**
     * @throws JsonException
     */
    #[DataProvider('providerInvoiceGraph')]
    public function testReportSaysWhichWayTheGraphWasRead(Graph $graph): void
    {
        $output = new BufferedOutput();
        (new JsonReporter(new DepthFirstTraversal(Direction::UsedBy)))
            ->report($graph, MethodNodeId::of('App\Domain\Money', 'add'), $output)
        ;

        self::assertStringContainsString('"direction": "used-by"', $output->fetch());
    }

    /**
     * @throws JsonException
     */
    #[DataProvider('providerInvoiceGraph')]
    public function testReportStopsAtTheLevelItIsBoundedAt(Graph $graph): void
    {
        $output = new BufferedOutput();
        (new JsonReporter(new DepthFirstTraversal(Direction::Uses), 1))
            ->report($graph, ClassNodeId::of('App\Domain\Invoice'), $output)
        ;

        self::assertStringNotContainsString('App\\\Domain\\\Money::add', $output->fetch());
    }

    /**
     * @throws JsonException
     */
    #[DataProvider('providerRecursiveGraph')]
    public function testReportNamesTheBranchItCutOnACycle(Graph $graph): void
    {
        $output = new BufferedOutput();
        (new JsonReporter(new DepthFirstTraversal(Direction::Uses)))
            ->report($graph, ClassNodeId::of('App\Domain\Invoice'), $output)
        ;

        self::assertStringContainsString('"truncated": "recursive"', $output->fetch());
    }

    /**
     * @return iterable<string, array{Graph}>
     */
    public static function providerRecursiveGraph(): iterable
    {
        $meta = new FileMeta('/project/src/Domain/Invoice.php', 12, 1);
        $invoice = new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, $meta);
        $total = new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true, $meta);
        $graph = new Graph();
        $graph->addNodes([$invoice, $total]);
        $graph->addEdges([
            new MethodEdge($invoice, $total, $meta),
            new MethodCallEdge($total, $total, $meta),
        ]);

        yield 'App\Domain\Invoice::total calls itself' => [$graph];
    }

    /**
     * @throws JsonException
     */
    #[DataProvider('providerSharedDependencyGraph')]
    public function testReportNamesTheBranchItCutOnASymbolExpandedElsewhere(Graph $graph): void
    {
        $output = new BufferedOutput();
        (new JsonReporter(new DepthFirstTraversal(Direction::Uses)))
            ->report($graph, ClassNodeId::of('App\Domain\Invoice'), $output)
        ;

        self::assertStringContainsString('"truncated": "repeated"', $output->fetch());
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

        yield 'both App\Domain\Invoice::total and ::lines call App\Domain\Money::add' => [$graph];
    }

    /**
     * @throws JsonException
     */
    #[DataProvider('providerInvoiceGraph')]
    public function testReportWritesNothingAtAllForASymbolTheGraphDoesNotHold(Graph $graph): void
    {
        $output = new BufferedOutput();
        (new JsonReporter(new DepthFirstTraversal(Direction::Uses)))
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
