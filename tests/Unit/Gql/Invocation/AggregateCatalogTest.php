<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Invocation;

use App\Gql\Argument\NumberArgument;
use App\Gql\Argument\TextArgument;
use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\DateTimeDatum;
use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumIdentity;
use App\Gql\Datum\DatumJson;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DatumOrder;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\FloatDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\PathDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\GqlException;
use App\Gql\Invocation\Accumulator;
use App\Gql\Invocation\AggregateCatalog;
use App\Gql\Invocation\AverageAccumulator;
use App\Gql\Invocation\CollectAccumulator;
use App\Gql\Invocation\CountAccumulator;
use App\Gql\Invocation\DistinctAccumulator;
use App\Gql\Invocation\ExtremeAccumulator;
use App\Gql\Invocation\SumAccumulator;
use App\Gql\StatusCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AggregateCatalog::class)]
#[UsesClass(BooleanDatum::class)]
#[UsesClass(DateTimeDatum::class)]
#[UsesClass(DatumIdentity::class)]
#[UsesClass(DatumJson::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DatumOrder::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(FloatDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(PathDatum::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(Datum::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(NumberArgument::class)]
#[UsesClass(TextArgument::class)]
#[UsesClass(Accumulator::class)]
#[UsesClass(AverageAccumulator::class)]
#[UsesClass(CollectAccumulator::class)]
#[UsesClass(CountAccumulator::class)]
#[UsesClass(DistinctAccumulator::class)]
#[UsesClass(ExtremeAccumulator::class)]
#[UsesClass(SumAccumulator::class)]
#[Small]
final class AggregateCatalogTest extends TestCase
{
    public function testIsAggregateRecognisesASummaryHoweverItIsCased(): void
    {
        self::assertTrue(AggregateCatalog::isAggregate('COUNT'));
    }

    public function testIsAggregateDoesNotTakeAnOrdinaryFunctionForOne(): void
    {
        self::assertFalse(AggregateCatalog::isAggregate('upper'));
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerSummariesAndWhatTheyMakeOfTwoNumbers')]
    public function testStartStartsTheSummaryTheQueryAsksForByName(string $name, string $expected): void
    {
        $summary = AggregateCatalog::start($name, false, false);
        $summary->accept(new IntegerDatum(1));
        $summary->accept(new IntegerDatum(3));

        self::assertSame($expected, $summary->result()->toText());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerSummariesAndWhatTheyMakeOfTwoNumbers(): iterable
    {
        yield 'how many there were' => ['count', '2'];

        yield 'what they add up to' => ['sum', '4'];

        yield 'what they come to on average' => ['avg', '2.0'];

        yield 'the smallest of them' => ['min', '1'];

        yield 'the largest of them' => ['max', '3'];

        yield 'all of them' => ['collect_list', '[1, 3]'];
    }

    /**
     * @throws GqlException
     */
    public function testStartWrapsWhateverSummaryWasAskedForWhenRepeatsAreDropped(): void
    {
        $summary = AggregateCatalog::start('count', true, false);
        $summary->accept(new IntegerDatum(1));
        $summary->accept(new IntegerDatum(1));

        self::assertSame('1', $summary->result()->toText());
    }

    /**
     * @throws GqlException
     */
    public function testStartCountsRowsRatherThanValuesWhenAskedOverRows(): void
    {
        $summary = AggregateCatalog::start('count', false, true);
        $summary->accept(new NullDatum());

        self::assertSame('1', $summary->result()->toText());
    }

    /**
     * @throws GqlException
     */
    public function testStartReportsANameThatBelongsToNoSummary(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('there is no aggregate function called "median"');

        AggregateCatalog::start('median', false, false);
    }

    public function testAllOffersCountingAmongTheSummaries(): void
    {
        self::assertContains('count', AggregateCatalog::all());
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerEverySummaryName')]
    public function testAllNamesOnlySummariesThatCanActuallyBeStarted(string $name): void
    {
        self::assertTrue(AggregateCatalog::isAggregate($name));

        AggregateCatalog::start($name, false, false);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerEverySummaryName(): iterable
    {
        foreach (AggregateCatalog::all() as $name) {
            yield $name => [$name];
        }
    }
}
