<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Invocation;

use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumIdentity;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\GqlException;
use App\Gql\Invocation\Accumulator;
use App\Gql\Invocation\AverageAccumulator;
use App\Gql\Invocation\CollectAccumulator;
use App\Gql\Invocation\CountAccumulator;
use App\Gql\Invocation\DistinctAccumulator;
use App\Gql\Invocation\ExtremeAccumulator;
use App\Gql\Invocation\SumAccumulator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AverageAccumulator::class)]
#[CoversClass(CollectAccumulator::class)]
#[CoversClass(CountAccumulator::class)]
#[CoversClass(DistinctAccumulator::class)]
#[CoversClass(ExtremeAccumulator::class)]
#[CoversClass(SumAccumulator::class)]
#[UsesClass(DatumIdentity::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(NullDatum::class)]
#[Small]
final class AccumulatorTest extends TestCase
{
    /**
     * @throws GqlException
     */
    #[DataProvider('providerEverySummaryAndWhatItAnswersToNothing')]
    public function testAcceptPassesOverAValueThatIsNotThere(Accumulator $summary, Datum $offeredNothing): void
    {
        $summary->accept(new NullDatum());

        self::assertEquals($offeredNothing, $summary->result());
    }

    #[DataProvider('providerEverySummaryAndWhatItAnswersToNothing')]
    public function testResultAnswersTheSameWhetherItWasOfferedNothingOrOnlyAbsentValues(Accumulator $summary, Datum $offeredNothing): void
    {
        self::assertEquals($offeredNothing, $summary->result());
    }

    /**
     * @return iterable<string, array{Accumulator, Datum}>
     */
    public static function providerEverySummaryAndWhatItAnswersToNothing(): iterable
    {
        yield 'how many there were' => [new CountAccumulator(), new IntegerDatum(0)];

        yield 'what they add up to' => [new SumAccumulator(), new NullDatum()];

        yield 'what they come to on average' => [new AverageAccumulator(), new NullDatum()];

        yield 'the smallest of them' => [new ExtremeAccumulator(), new NullDatum()];

        yield 'the largest of them' => [new ExtremeAccumulator(true), new NullDatum()];

        yield 'all of them' => [new CollectAccumulator(), new NullDatum()];

        yield 'each of them only once' => [new DistinctAccumulator(new CountAccumulator()), new IntegerDatum(0)];
    }
}
