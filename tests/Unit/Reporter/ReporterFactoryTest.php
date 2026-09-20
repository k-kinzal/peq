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
use App\Config\OutputFormat;
use App\Reporter\DotReporter\DotReporter;
use App\Reporter\JsonReporter\JsonReporter;
use App\Reporter\Reporter;
use App\Reporter\ReporterFactory;
use App\Reporter\TableReporter\TableReporter;
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
#[UsesClass(\App\Reporter\Continuation::class)]
#[UsesClass(\App\Reporter\Expansion::class)]
#[UsesClass(\App\Reporter\DotReporter\DotCursor::class)]
#[UsesClass(\App\Reporter\DotReporter\StatementRenderer::class)]
#[UsesClass(\App\Reporter\JsonReporter\JsonCursor::class)]
#[UsesClass(\App\Reporter\TableReporter\TableCursor::class)]
#[UsesClass(DotReporter::class)]
#[UsesClass(JsonReporter::class)]
#[UsesClass(TableReporter::class)]
#[UsesClass(\App\Reporter\Traversal\DepthFirstTraversal::class)]
#[UsesClass(\App\Reporter\Traversal\DepthFirstWalk::class)]
#[UsesClass(\App\Reporter\TreeReporter\LineRenderer::class)]
#[UsesClass(\App\Reporter\TreeReporter\TreeCursor::class)]
#[UsesClass(TreeReporter::class)]
#[UsesClass(\App\Reporter\TreeReporter\TreeReporterOptions::class)]
#[Small]
final class ReporterFactoryTest extends TestCase
{
    public function testCreateBuildsTheTreeReporterWhenNoFormatIsAsked(): void
    {
        self::assertInstanceOf(TreeReporter::class, (new ReporterFactory())->create(new Config('.', Direction::Uses)));
    }

    /**
     * @param class-string<Reporter> $expected
     */
    #[DataProvider('providerTheReporterEachFormatIsWrittenBy')]
    public function testCreateBuildsTheReporterTheFormatNames(OutputFormat $format, string $expected): void
    {
        self::assertInstanceOf($expected, (new ReporterFactory())->create(new Config('.', Direction::Uses, output: $format)));
    }

    /**
     * @return iterable<string, array{OutputFormat, class-string<Reporter>}>
     */
    public static function providerTheReporterEachFormatIsWrittenBy(): iterable
    {
        yield 'an indented tree' => [OutputFormat::Tree, TreeReporter::class];

        yield 'a JSON document' => [OutputFormat::Json, JsonReporter::class];

        yield 'a Graphviz digraph' => [OutputFormat::Dot, DotReporter::class];

        yield 'a table of rows' => [OutputFormat::Table, TableReporter::class];
    }

    #[DataProvider('providerEveryFormatOverTheInvoiceGraph')]
    public function testCreateAnswersEveryFormatWithAReporterThatWritesSomething(OutputFormat $format, Graph $graph): void
    {
        $output = new BufferedOutput();
        (new ReporterFactory())
            ->create(new Config('.', Direction::Uses, output: $format))
            ->report($graph, ClassNodeId::of('App\Domain\Invoice'), $output)
        ;

        self::assertNotSame('', $output->fetch());
    }

    /**
     * @return iterable<string, array{OutputFormat, Graph}>
     */
    public static function providerEveryFormatOverTheInvoiceGraph(): iterable
    {
        $meta = new FileMeta('/project/src/Domain/Invoice.php', 12, 1);
        $invoice = new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, $meta);
        $total = new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true, $meta);
        $graph = new Graph();
        $graph->addNodes([$invoice, $total]);
        $graph->addEdges([new MethodEdge($invoice, $total, $meta)]);

        foreach (OutputFormat::cases() as $format) {
            yield $format->value => [$format, $graph];
        }
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
