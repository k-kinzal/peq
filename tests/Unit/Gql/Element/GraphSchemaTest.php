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
#[UsesClass(ResultColumn::class)]
#[UsesClass(ResultRow::class)]
#[UsesClass(ResultTable::class)]
#[Small]
final class GraphSchemaTest extends TestCase
{
    public function testTableSaysWhatEachNameIsAndWhereItHasOneItsType(): void
    {
        self::assertSame(['category', 'name', 'type'], GraphSchema::table()->headings());
    }

    #[DataProvider('providerVocabularyAQueryIsWrittenFrom')]
    public function testTableCoversTheWholeVocabularyAQueryIsWrittenFrom(string $name): void
    {
        $named = array_map(static fn ($value): string => $value->toText(), GraphSchema::table()->column(1));

        self::assertContains($name, $named);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerVocabularyAQueryIsWrittenFrom(): iterable
    {
        yield 'a family of symbol a pattern selects by' => ['Callable'];

        yield 'a family of relation a pattern selects by' => ['call'];

        yield 'something a query can ask a symbol' => ['visibility'];

        yield 'something a query can ask a relation' => ['line'];

        yield 'a function a query can call' => ['size'];

        yield 'a way of summarising a group of rows' => ['count'];
    }

    public function testTableSaysWhichCategoryEachNameBelongsTo(): void
    {
        $categories = array_map(static fn ($value): string => $value->toText(), GraphSchema::table()->column(0));

        self::assertSame(
            ['node label', 'edge label', 'node property', 'edge property', 'function', 'aggregate'],
            array_values(array_unique($categories)),
        );
    }

    public function testNamedReportsANameWithNoTypeToReportAgainstIt(): void
    {
        self::assertSame('', GraphSchema::named('node label', ['Class'])[0]->value('type')->toText());
    }

    public function testNamedReportsNothingForACategoryWithNoNames(): void
    {
        self::assertSame([], GraphSchema::named('node label', []));
    }

    public function testTypedReportsAPropertyWithTheTypeItHolds(): void
    {
        self::assertSame('INT64', GraphSchema::typed('node property', ['line' => 'INT64'])[0]->value('type')->toText());
    }

    public function testTypedReportsThePropertyUnderTheNameAQueryAsksItBy(): void
    {
        self::assertSame('line', GraphSchema::typed('node property', ['line' => 'INT64'])[0]->value('name')->toText());
    }
}
