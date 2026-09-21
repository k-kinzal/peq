<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Element;

use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\NodeKind;
use App\Gql\Binding\BindingRow;
use App\Gql\Binding\BindingTable;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\StringDatum;
use App\Gql\Element\EdgeLabels;
use App\Gql\Element\EdgeProperties;
use App\Gql\Element\GraphSchema;
use App\Gql\Element\NodeLabels;
use App\Gql\Element\NodeProperties;
use App\Gql\Invocation\AggregateCatalog;
use App\Gql\Invocation\FunctionCatalog;
use App\Gql\ReservedWords;
use App\Gql\Result\ResultColumn;
use App\Gql\Result\ResultRow;
use App\Gql\Result\ResultTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(GraphSchema::class)]
#[UsesClass(EdgeKind::class)]
#[UsesClass(NodeKind::class)]
#[UsesClass(BindingRow::class)]
#[UsesClass(BindingTable::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(EdgeLabels::class)]
#[UsesClass(EdgeProperties::class)]
#[UsesClass(NodeLabels::class)]
#[UsesClass(NodeProperties::class)]
#[UsesClass(AggregateCatalog::class)]
#[UsesClass(FunctionCatalog::class)]
#[UsesClass(ReservedWords::class)]
#[UsesClass(ResultColumn::class)]
#[UsesClass(ResultRow::class)]
#[UsesClass(ResultTable::class)]
#[Small]
final class GraphSchemaTest extends TestCase
{
    public function testTableSaysWhatEachNameIsAndWhereItHasOneItsType(): void
    {
        self::assertEquals(
            [new ResultColumn('category', 'STRING'), new ResultColumn('name', 'STRING'), new ResultColumn('type', 'STRING')],
            GraphSchema::table()->columns,
        );
    }

    #[DataProvider('providerVocabularyAQueryIsWrittenFrom')]
    public function testTableCoversTheWholeVocabularyAQueryIsWrittenFrom(ResultRow $row): void
    {
        self::assertContainsEquals($row, GraphSchema::table()->rows);
    }

    /**
     * @return iterable<string, array{ResultRow}>
     */
    public static function providerVocabularyAQueryIsWrittenFrom(): iterable
    {
        yield 'a family of symbol a pattern selects by' => [
            new ResultRow([new StringDatum('node label'), new StringDatum('Callable'), new StringDatum('')]),
        ];

        yield 'a family of relation a pattern selects by, in back quotes because GQL reserves the word' => [
            new ResultRow([new StringDatum('edge label'), new StringDatum('`call`'), new StringDatum('')]),
        ];

        yield 'something a query can ask a symbol' => [
            new ResultRow([new StringDatum('node property'), new StringDatum('visibility'), new StringDatum('STRING')]),
        ];

        yield 'something a query can ask a relation' => [
            new ResultRow([new StringDatum('edge property'), new StringDatum('line'), new StringDatum('INT64')]),
        ];

        yield 'a function a query can call' => [
            new ResultRow([new StringDatum('function'), new StringDatum('size'), new StringDatum('')]),
        ];

        yield 'a way of summarising a group of rows' => [
            new ResultRow([new StringDatum('aggregate'), new StringDatum('count'), new StringDatum('')]),
        ];
    }

    public function testTableStartsWithTheLabelsASymbolIsSelectedBy(): void
    {
        self::assertEquals(
            new ResultRow([new StringDatum('node label'), new StringDatum('Class'), new StringDatum('')]),
            GraphSchema::table()->rows[0],
        );
    }

    public function testNamedReportsANameWithNoTypeToReportAgainstIt(): void
    {
        self::assertEquals(
            [new BindingRow(['category' => new StringDatum('function'), 'name' => new StringDatum('size'), 'type' => new StringDatum('')])],
            GraphSchema::named('function', ['size']),
        );
    }

    public function testNamedReportsNothingForACategoryWithNoNames(): void
    {
        self::assertSame([], GraphSchema::named('function', []));
    }

    public function testTypedReportsAPropertyUnderTheNameAQueryAsksItByWithTheTypeItHolds(): void
    {
        self::assertEquals(
            [new BindingRow(['category' => new StringDatum('node property'), 'name' => new StringDatum('line'), 'type' => new StringDatum('INT64')])],
            GraphSchema::typed('node property', ['line' => 'INT64']),
        );
    }

    public function testTypedReportsAPropertyNamedAfterAReservedWordInBackQuotes(): void
    {
        self::assertEquals(
            [new BindingRow(['category' => new StringDatum('node property'), 'name' => new StringDatum('`value`'), 'type' => new StringDatum('STRING')])],
            GraphSchema::typed('node property', ['value' => 'STRING']),
        );
    }

    public function testLabelledReportsALabelGqlLeavesFreeAsItIs(): void
    {
        self::assertEquals(
            [new BindingRow(['category' => new StringDatum('node label'), 'name' => new StringDatum('Method'), 'type' => new StringDatum('')])],
            GraphSchema::labelled('node label', ['Method']),
        );
    }

    public function testLabelledReportsALabelGqlReservesInBackQuotes(): void
    {
        self::assertEquals(
            [new BindingRow(['category' => new StringDatum('node label'), 'name' => new StringDatum('`Function`'), 'type' => new StringDatum('')])],
            GraphSchema::labelled('node label', ['Function']),
        );
    }

    public function testLabelledReportsNothingForACategoryWithNoLabels(): void
    {
        self::assertSame([], GraphSchema::labelled('node label', []));
    }
}
