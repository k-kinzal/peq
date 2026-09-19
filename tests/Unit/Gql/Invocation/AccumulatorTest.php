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
#[CoversClass(Accumulator::class)]
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
#[UsesClass(AggregateCatalog::class)]
#[UsesClass(AverageAccumulator::class)]
#[UsesClass(CollectAccumulator::class)]
#[UsesClass(CountAccumulator::class)]
#[UsesClass(DistinctAccumulator::class)]
#[UsesClass(ExtremeAccumulator::class)]
#[UsesClass(SumAccumulator::class)]
#[Small]
final class AccumulatorTest extends TestCase
{
    /**
     * @throws GqlException
     */
    #[DataProvider('providerEverySummary')]
    public function testAcceptPassesOverAValueThatIsNotThere(Accumulator $summary, string $offeredNothing): void
    {
        $summary->accept(new NullDatum());

        self::assertSame($offeredNothing, $summary->result()->toText());
    }

    #[DataProvider('providerEverySummary')]
    public function testResultAnswersTheSameWhetherItWasOfferedNothingOrOnlyAbsentValues(
        Accumulator $summary,
        string $offeredNothing,
    ): void {
        self::assertSame($offeredNothing, $summary->result()->toText());
    }

    /**
     * @return iterable<string, array{Accumulator, string}>
     */
    public static function providerEverySummary(): iterable
    {
        yield 'how many there were' => [new CountAccumulator(), '0'];

        yield 'what they add up to' => [new SumAccumulator(), 'NULL'];

        yield 'what they come to on average' => [new AverageAccumulator(), 'NULL'];

        yield 'the smallest of them' => [new ExtremeAccumulator(), 'NULL'];

        yield 'the largest of them' => [new ExtremeAccumulator(true), 'NULL'];

        yield 'all of them' => [new CollectAccumulator(), 'NULL'];

        yield 'each of them only once' => [new DistinctAccumulator(new CountAccumulator()), '0'];
    }
}
