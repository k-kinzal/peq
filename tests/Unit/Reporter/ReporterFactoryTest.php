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
use App\Config\Config;
use App\Reporter\ReporterFactory;
use App\Reporter\TreeReporter\TreeReporter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @internal
 */
#[CoversClass(ReporterFactory::class)]
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
#[UsesClass(Config::class)]
#[UsesClass(\App\Config\DebugAnalyzerConfig::class)]
#[UsesClass(\App\Reporter\Traversal\DepthFirstTraversal::class)]
#[UsesClass(\App\Reporter\Traversal\DepthFirstWalk::class)]
#[UsesClass(\App\Reporter\TreeReporter\LineRenderer::class)]
#[UsesClass(\App\Reporter\TreeReporter\TreeCursor::class)]
#[UsesClass(TreeReporter::class)]
#[UsesClass(\App\Reporter\TreeReporter\TreeReporterOptions::class)]
#[Small]
final class ReporterFactoryTest extends TestCase
{
    public function testCreateBuildsTheTreeReporterTheProjectShipsWith(): void
    {
        self::assertInstanceOf(TreeReporter::class, (new ReporterFactory())->create(new Config('.', Direction::Uses)));
    }

    #[DataProvider('providerBothDirections')]
    public function testCreateGivesTheReporterTheDirectionTheConfigurationAsksFor(Direction $direction, string $expectedSecondLine, Graph $graph): void
    {
        $output = new BufferedOutput();
        (new ReporterFactory())
            ->create(new Config('.', $direction))
            ->report($graph, ClassNodeId::of('App\Domain\Invoice'), $output)
        ;

        self::assertStringContainsString($expectedSecondLine, $output->fetch());
    }

    /**
     * @return iterable<string, array{Direction, string, Graph}>
     */
    public static function providerBothDirections(): iterable
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

        yield 'away from the subject' => [Direction::Uses, 'App\Domain\Invoice::total', $graph];

        yield 'towards the subject' => [Direction::UsedBy, 'App\Domain\Invoice', $graph];
    }

    #[DataProvider('providerInvoiceGraph')]
    public function testCreateGivesTheReporterTheLevelBoundTheConfigurationAsksFor(Graph $graph): void
    {
        $output = new BufferedOutput();
        (new ReporterFactory())
            ->create(new Config('.', Direction::Uses, level: 1))
            ->report($graph, ClassNodeId::of('App\Domain\Invoice'), $output)
        ;

        self::assertStringNotContainsString('App\Domain\Money::add', $output->fetch());
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

    public function testCreateBuildsAFreshReporterForEachConfiguration(): void
    {
        $factory = new ReporterFactory();

        self::assertNotSame(
            $factory->create(new Config('.', Direction::Uses)),
            $factory->create(new Config('.', Direction::Uses)),
        );
    }
}
