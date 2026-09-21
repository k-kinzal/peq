<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Query;

use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\PathDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\Result\ResultColumn;
use App\Gql\Result\ResultRow;
use App\Gql\Result\ResultTable;
use App\Gql\StatusCode;
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
use App\Reporter\Query\DatumJson;
use App\Reporter\Query\DiagramWriter;
use App\Reporter\Query\DotWriter;
use App\Reporter\Query\JsonWriter;
use App\Reporter\Query\QueryReporter;
use App\Reporter\Query\ResultElements;
use App\Reporter\Query\TableWriter;
use App\Reporter\Query\TreeStep;
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
#[CoversClass(DiagramWriter::class)]
#[CoversClass(DotWriter::class)]
#[CoversClass(JsonWriter::class)]
#[CoversClass(TableWriter::class)]
#[CoversClass(TreeWriter::class)]
#[UsesClass(DatumJson::class)]
#[UsesClass(ResultElements::class)]
#[UsesClass(TreeStep::class)]
#[UsesClass(Diagram::class)]
#[UsesClass(DiagramEdge::class)]
#[UsesClass(DiagramNode::class)]
#[UsesClass(MermaidRenderer::class)]
#[UsesClass(TerminalRenderer::class)]
#[UsesClass(ResultColumn::class)]
#[UsesClass(ResultRow::class)]
#[UsesClass(ResultTable::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(PathDatum::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(DiagramCanvas::class)]
#[UsesClass(DiagramLayout::class)]
#[UsesClass(LaneRouting::class)]
#[UsesClass(LayerOrdering::class)]
#[UsesClass(LayeredLayout::class)]
#[UsesClass(LayoutItem::class)]
#[UsesClass(RowPlacement::class)]
#[Small]
final class QueryReporterTest extends TestCase
{
    #[DataProvider('providerEveryWayOfWritingAnAnswerThatFoundNothing')]
    public function testReportWritesAnAnswerThatFoundNothing(QueryReporter $reporter, string $expected): void
    {
        $output = new BufferedOutput();

        $reporter->report(ResultTable::nothing(), $output);

        self::assertSame($expected, $output->fetch());
    }

    /**
     * @return iterable<string, array{QueryReporter, string}>
     */
    public static function providerEveryWayOfWritingAnAnswerThatFoundNothing(): iterable
    {
        yield 'a table, which leaves the status to say it' => [new TableWriter(), ''];

        yield 'fields a program can address, which carry the status' => [
            new JsonWriter(),
            <<<'JSON'
                {"status":"02000","condition":"note: no data","columns":[],"rows":[]}

                JSON,
        ];

        yield 'a drawing in the terminal' => [new DiagramWriter(null, new TerminalRenderer()), ''];

        yield 'a flowchart' => [new DiagramWriter(null, new MermaidRenderer()), ''];

        yield 'a digraph' => [new DotWriter(), ''];

        yield 'a tree of the paths it bound' => [new TreeWriter(), ''];
    }

    #[DataProvider('providerEveryWayOfWritingAnAnswerHoldingOneSymbol')]
    public function testReportWritesAnAnswerHoldingOneSymbol(QueryReporter $reporter, string $expected): void
    {
        $invoice = new NodeDatum('App\Invoice', ['Class'], ['kind' => new StringDatum('class')]);
        $output = new BufferedOutput();

        $reporter->report(new ResultTable([new ResultColumn('p', 'NODE')], [new ResultRow([$invoice])]), $output);

        self::assertSame($expected, $output->fetch());
    }

    /**
     * @return iterable<string, array{QueryReporter, string}>
     */
    public static function providerEveryWayOfWritingAnAnswerHoldingOneSymbol(): iterable
    {
        yield 'a table' => [
            new TableWriter(),
            <<<'TABLE'
                +-------------+
                | p (NODE)    |
                +-------------+
                | App\Invoice |
                +-------------+

                TABLE,
        ];

        yield 'fields a program can address' => [
            new JsonWriter(),
            <<<'JSON'
                {"status":"00000","condition":"note: successful completion","columns":[{"name":"p","type":"NODE"}],"rows":[[{"id":"App\\Invoice","labels":["Class"],"properties":{"kind":"class"}}]]}

                JSON,
        ];

        yield 'a drawing in the terminal' => [new DiagramWriter(null, new TerminalRenderer()), "App\\Invoice\n"];

        yield 'a flowchart' => [
            new DiagramWriter(null, new MermaidRenderer()),
            <<<'MERMAID'
                flowchart LR
                    n1["App\Invoice"]

                MERMAID,
        ];

        yield 'a digraph' => [
            new DotWriter(),
            <<<'DOT'
                digraph peq {
                    rankdir=LR;
                    "App\\Invoice" [label="App\\Invoice\nclass"];
                }

                DOT,
        ];

        yield 'a tree of the paths it bound, which is none' => [new TreeWriter(), ''];
    }

    #[DataProvider('providerEveryWayOfWritingAnAnswerHoldingOnePath')]
    public function testReportWritesAnAnswerHoldingOnePath(QueryReporter $reporter, string $expected): void
    {
        $path = PathDatum::at(new NodeDatum('a'))->continuedBy(new EdgeDatum('e', ['calls'], [], 'a', 'b'), new NodeDatum('b'));
        $output = new BufferedOutput();

        $reporter->report(new ResultTable([new ResultColumn('p', 'PATH')], [new ResultRow([$path])]), $output);

        self::assertSame($expected, $output->fetch());
    }

    /**
     * @return iterable<string, array{QueryReporter, string}>
     */
    public static function providerEveryWayOfWritingAnAnswerHoldingOnePath(): iterable
    {
        yield 'a table' => [
            new TableWriter(),
            <<<'TABLE'
                +----------------+
                | p (PATH)       |
                +----------------+
                | a -[calls]-> b |
                +----------------+

                TABLE,
        ];

        yield 'fields a program can address' => [
            new JsonWriter(),
            <<<'JSON'
                {"status":"00000","condition":"note: successful completion","columns":[{"name":"p","type":"PATH"}],"rows":[[[{"id":"a","labels":[],"properties":{}},{"id":"e","labels":["calls"],"origin":"a","target":"b","properties":{}},{"id":"b","labels":[],"properties":{}}]]]}

                JSON,
        ];

        yield 'a drawing in the terminal' => [new DiagramWriter(null, new TerminalRenderer()), "a ──▶ b\n"];

        yield 'a flowchart' => [
            new DiagramWriter(null, new MermaidRenderer()),
            <<<'MERMAID'
                flowchart LR
                    n1["a"]
                    n2["b"]
                    n1 -->|"calls"| n2

                MERMAID,
        ];

        yield 'a digraph' => [
            new DotWriter(),
            <<<'DOT'
                digraph peq {
                    rankdir=LR;
                    "a" [label="a"];
                    "b" [label="b"];
                    "a" -> "b" [label="calls"];
                }

                DOT,
        ];

        yield 'a tree' => [
            new TreeWriter(),
            <<<'TREE'
                a
                └── calls ──> b

                TREE,
        ];
    }
}
