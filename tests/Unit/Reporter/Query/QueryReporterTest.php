<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Query;

use App\Gql\Binding\BindingRow;
use App\Gql\Binding\BindingTable;
use App\Gql\Datum\DatumJson;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\PathDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\Element\ElementGraph;
use App\Gql\Result\ResultColumn;
use App\Gql\Result\ResultRow;
use App\Gql\Result\ResultTable;
use App\Gql\StatusCode;
use App\Reporter\Diagram\Diagram;
use App\Reporter\Diagram\DiagramEdge;
use App\Reporter\Diagram\DiagramNode;
use App\Reporter\Diagram\DiagramRenderer;
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
#[CoversClass(QueryReporter::class)]
#[UsesClass(BindingRow::class)]
#[UsesClass(BindingTable::class)]
#[UsesClass(DatumJson::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(PathDatum::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(ResultColumn::class)]
#[UsesClass(ResultRow::class)]
#[UsesClass(ResultTable::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(ElementGraph::class)]
#[UsesClass(DiagramWriter::class)]
#[UsesClass(DotWriter::class)]
#[UsesClass(JsonWriter::class)]
#[UsesClass(TableWriter::class)]
#[UsesClass(TreeWriter::class)]
#[UsesClass(Diagram::class)]
#[UsesClass(DiagramEdge::class)]
#[UsesClass(DiagramNode::class)]
#[UsesClass(DiagramRenderer::class)]
#[UsesClass(ResultElements::class)]
#[UsesClass(TreeStep::class)]
#[Small]
final class QueryReporterTest extends TestCase
{
    #[DataProvider('providerEveryWayOfWritingAnAnswer')]
    public function testReportWritesNothingForAnAnswerThatFoundNothing(QueryReporter $reporter): void
    {
        $output = new BufferedOutput();
        $reporter->report(ResultTable::nothing(), $output);

        self::assertSame('', $output->fetch());
    }

    /**
     * @return iterable<string, array{QueryReporter}>
     */
    public static function providerEveryWayOfWritingAnAnswer(): iterable
    {
        yield 'a table to scan' => [new TableWriter()];

        yield 'a drawing of how the answer is wired' => [new DiagramWriter()];

        yield 'a digraph for a renderer' => [new DotWriter()];

        yield 'a tree of the paths it bound' => [new TreeWriter()];
    }

    #[DataProvider('providerEveryWayOfWritingAnAnswerToLookAt')]
    public function testReportWritesSomethingForAnAnswerThatFoundSomething(QueryReporter $reporter): void
    {
        $row = BindingRow::unit()->with('p', new NodeDatum('App\Invoice', ['Class']));
        $output = new BufferedOutput();
        $reporter->report(ResultTable::of(['p'], new BindingTable([$row])), $output);

        self::assertNotSame('', $output->fetch());
    }

    /**
     * @return iterable<string, array{QueryReporter}>
     */
    public static function providerEveryWayOfWritingAnAnswerToLookAt(): iterable
    {
        yield 'a table to scan' => [new TableWriter()];

        yield 'fields a program can address' => [new JsonWriter()];

        yield 'a drawing of how the answer is wired' => [new DiagramWriter()];

        yield 'a digraph for a renderer' => [new DotWriter()];
    }

    public function testReportWritesTheStatusEvenForAnAnswerThatFoundNothing(): void
    {
        $output = new BufferedOutput();
        (new JsonWriter())->report(ResultTable::nothing(), $output);

        self::assertStringContainsString('"status":"02000"', $output->fetch());
    }
}
