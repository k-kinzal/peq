<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Query;

use App\Analyzer\Graph\Direction;
use App\Config\AnalyzerKind;
use App\Config\Config;
use App\Config\ConfigException;
use App\Config\DebugAnalyzerConfig;
use App\Config\OutputFormat;
use App\Config\RawConfig;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Element\ElementGraph;
use App\Gql\Result\ResultColumn;
use App\Gql\Result\ResultRow;
use App\Gql\Result\ResultTable;
use App\Reporter\Diagram\Diagram;
use App\Reporter\Diagram\DiagramCanvas;
use App\Reporter\Diagram\DiagramEdge;
use App\Reporter\Diagram\DiagramNode;
use App\Reporter\Diagram\Layout\DiagramLayout;
use App\Reporter\Diagram\Layout\LaneRouting;
use App\Reporter\Diagram\Layout\LayeredLayout;
use App\Reporter\Diagram\Layout\LayerOrdering;
use App\Reporter\Diagram\Layout\LayoutItem;
use App\Reporter\Diagram\Layout\RowPlacement;
use App\Reporter\Diagram\MermaidRenderer;
use App\Reporter\Diagram\TerminalRenderer;
use App\Reporter\Query\DiagramWriter;
use App\Reporter\Query\DotWriter;
use App\Reporter\Query\JsonWriter;
use App\Reporter\Query\QueryReporterFactory;
use App\Reporter\Query\ResultElements;
use App\Reporter\Query\TableWriter;
use App\Reporter\Query\TreeWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @internal
 */
#[CoversClass(QueryReporterFactory::class)]
#[UsesClass(Config::class)]
#[UsesClass(OutputFormat::class)]
#[UsesClass(AnalyzerKind::class)]
#[UsesClass(ConfigException::class)]
#[UsesClass(RawConfig::class)]
#[UsesClass(Direction::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(ElementGraph::class)]
#[UsesClass(ResultColumn::class)]
#[UsesClass(ResultRow::class)]
#[UsesClass(ResultTable::class)]
#[UsesClass(Diagram::class)]
#[UsesClass(DiagramEdge::class)]
#[UsesClass(DiagramNode::class)]
#[UsesClass(MermaidRenderer::class)]
#[UsesClass(TerminalRenderer::class)]
#[UsesClass(DiagramWriter::class)]
#[UsesClass(DotWriter::class)]
#[UsesClass(JsonWriter::class)]
#[UsesClass(ResultElements::class)]
#[UsesClass(TableWriter::class)]
#[UsesClass(TreeWriter::class)]
#[UsesClass(DebugAnalyzerConfig::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(DiagramCanvas::class)]
#[UsesClass(DiagramLayout::class)]
#[UsesClass(LaneRouting::class)]
#[UsesClass(LayerOrdering::class)]
#[UsesClass(LayeredLayout::class)]
#[UsesClass(LayoutItem::class)]
#[UsesClass(RowPlacement::class)]
#[Small]
final class QueryReporterFactoryTest extends TestCase
{
    /**
     * @throws ConfigException
     */
    #[DataProvider('providerFormatsAndTheReporterTheyAskFor')]
    public function testCreateBuildsTheReporterTheConfigurationAsksFor(string $format, string $expected): void
    {
        $config = Config::fromArray(['basePath' => '.', 'direction' => 'uses', 'type' => 'native', 'output' => $format]);

        self::assertSame($expected, (new QueryReporterFactory())->create($config)::class);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerFormatsAndTheReporterTheyAskFor(): iterable
    {
        yield 'a table to scan' => ['table', TableWriter::class];

        yield 'fields a program can address' => ['json', JsonWriter::class];

        yield 'a drawing in the terminal' => ['graph', DiagramWriter::class];

        yield 'a flowchart for a page that renders one' => ['mermaid', DiagramWriter::class];

        yield 'a digraph for a renderer' => ['dot', DotWriter::class];

        yield 'a tree of the paths it bound' => ['tree', TreeWriter::class];
    }

    /**
     * @throws ConfigException
     */
    public function testCreateGivesTheGraphToTheDrawingInTheTerminal(): void
    {
        $config = Config::fromArray(['basePath' => '.', 'direction' => 'uses', 'type' => 'native', 'output' => 'graph']);
        $call = new EdgeDatum('a|method-call|b', ['methodCall', 'call', 'usage'], [], 'a', 'b');
        $graph = new ElementGraph(['a' => new NodeDatum('a'), 'b' => new NodeDatum('b')], ['a' => [$call]], ['b' => [$call]]);
        $answered = new ResultTable(
            [new ResultColumn('x', 'NODE'), new ResultColumn('y', 'NODE')],
            [new ResultRow([new NodeDatum('a'), new NodeDatum('b')])],
        );
        $output = new BufferedOutput();

        (new QueryReporterFactory())->create($config, $graph)->report($answered, $output);

        self::assertSame("a ──▶ b\n", $output->fetch());
    }

    /**
     * @throws ConfigException
     */
    public function testCreateGivesTheGraphToTheMermaidFlowchart(): void
    {
        $config = Config::fromArray(['basePath' => '.', 'direction' => 'uses', 'type' => 'native', 'output' => 'mermaid']);
        $call = new EdgeDatum('a|method-call|b', ['methodCall', 'call', 'usage'], [], 'a', 'b');
        $graph = new ElementGraph(['a' => new NodeDatum('a'), 'b' => new NodeDatum('b')], ['a' => [$call]], ['b' => [$call]]);
        $answered = new ResultTable(
            [new ResultColumn('x', 'NODE'), new ResultColumn('y', 'NODE')],
            [new ResultRow([new NodeDatum('a'), new NodeDatum('b')])],
        );
        $output = new BufferedOutput();

        (new QueryReporterFactory())->create($config, $graph)->report($answered, $output);

        self::assertSame(
            <<<'MERMAID'
                flowchart LR
                    n1["a"]
                    n2["b"]
                    n1 -->|"methodCall"| n2

                MERMAID,
            $output->fetch(),
        );
    }

    /**
     * @throws ConfigException
     */
    public function testCreateGivesTheGraphToTheDigraph(): void
    {
        $config = Config::fromArray(['basePath' => '.', 'direction' => 'uses', 'type' => 'native', 'output' => 'dot']);
        $call = new EdgeDatum('a|method-call|b', ['methodCall', 'call', 'usage'], [], 'a', 'b');
        $graph = new ElementGraph(['a' => new NodeDatum('a'), 'b' => new NodeDatum('b')], ['a' => [$call]], ['b' => [$call]]);
        $answered = new ResultTable(
            [new ResultColumn('x', 'NODE'), new ResultColumn('y', 'NODE')],
            [new ResultRow([new NodeDatum('a'), new NodeDatum('b')])],
        );
        $output = new BufferedOutput();

        (new QueryReporterFactory())->create($config, $graph)->report($answered, $output);

        self::assertSame(
            <<<'DOT'
                digraph peq {
                    rankdir=LR;
                    "a" [label="a"];
                    "b" [label="b"];
                    "a" -> "b" [label="methodCall"];
                }

                DOT,
            $output->fetch(),
        );
    }

    /**
     * @throws ConfigException
     */
    public function testCreateDrawsOnlyWhatTheAnswerHoldsWhenThereIsNoGraph(): void
    {
        $config = Config::fromArray(['basePath' => '.', 'direction' => 'uses', 'type' => 'native', 'output' => 'mermaid']);
        $answered = new ResultTable(
            [new ResultColumn('x', 'NODE'), new ResultColumn('y', 'NODE')],
            [new ResultRow([new NodeDatum('a'), new NodeDatum('b')])],
        );
        $output = new BufferedOutput();

        (new QueryReporterFactory())->create($config)->report($answered, $output);

        self::assertSame(
            <<<'MERMAID'
                flowchart LR
                    n1["a"]
                    n2["b"]

                MERMAID,
            $output->fetch(),
        );
    }
}
