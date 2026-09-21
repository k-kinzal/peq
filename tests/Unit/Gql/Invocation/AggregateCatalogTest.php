<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Invocation;

use App\Gql\Argument\ExactArithmetic;
use App\Gql\Argument\NumberArgument;
use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumIdentity;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DatumOrder;
use App\Gql\Datum\DecimalDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\GqlException;
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
#[UsesClass(AverageAccumulator::class)]
#[UsesClass(CollectAccumulator::class)]
#[UsesClass(CountAccumulator::class)]
#[UsesClass(DatumIdentity::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DatumOrder::class)]
#[UsesClass(DecimalDatum::class)]
#[UsesClass(DistinctAccumulator::class)]
#[UsesClass(ExactArithmetic::class)]
#[UsesClass(ExtremeAccumulator::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(NumberArgument::class)]
#[UsesClass(StatusCode::class)]
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
    #[DataProvider('providerSummariesAndWhatTheyMakeOfOneAndThree')]
    public function testStartStartsTheSummaryTheQueryAsksForByName(string $name, Datum $expected): void
    {
        $summary = AggregateCatalog::start($name, false, false);
        $summary->accept(new IntegerDatum(1));
        $summary->accept(new IntegerDatum(3));

        self::assertEquals($expected, $summary->result());
    }

    /**
     * @return iterable<string, array{string, Datum}>
     */
    public static function providerSummariesAndWhatTheyMakeOfOneAndThree(): iterable
    {
        yield 'how many there were' => ['count', new IntegerDatum(2)];

        yield 'what they add up to' => ['sum', new IntegerDatum(4)];

        yield 'what they come to on average' => ['avg', new DecimalDatum(2000000, 6)];

        yield 'the smallest of them' => ['min', new IntegerDatum(1)];

        yield 'the largest of them' => ['max', new IntegerDatum(3)];

        yield 'all of them' => ['collect_list', new ListDatum([new IntegerDatum(1), new IntegerDatum(3)])];

        yield 'a summary asked for in capitals' => ['MAX', new IntegerDatum(3)];
    }

    /**
     * @throws GqlException
     */
    public function testStartWrapsWhateverSummaryWasAskedForWhenRepeatsAreDropped(): void
    {
        $summary = AggregateCatalog::start('count', true, false);
        $summary->accept(new IntegerDatum(1));
        $summary->accept(new IntegerDatum(1));

        self::assertEquals(new IntegerDatum(1), $summary->result());
    }

    /**
     * @throws GqlException
     */
    public function testStartCountsRowsRatherThanValuesWhenAskedOverRows(): void
    {
        $summary = AggregateCatalog::start('count', false, true);
        $summary->accept(new NullDatum());

        self::assertEquals(new IntegerDatum(1), $summary->result());
    }

    /**
     * @throws GqlException
     */
    public function testStartReportsANameThatBelongsToNoSummary(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[42000] error: syntax error or access rule violation: there is no aggregate function called "median"');

        AggregateCatalog::start('median', false, false);
    }

    public function testAllNamesEverySummaryGqlDefines(): void
    {
        self::assertSame(['count', 'sum', 'avg', 'min', 'max', 'collect_list'], AggregateCatalog::all());
    }
}
