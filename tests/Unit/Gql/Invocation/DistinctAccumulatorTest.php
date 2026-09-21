<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Invocation;

use App\Gql\Datum\DatumIdentity;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DatumOrder;
use App\Gql\Datum\DecimalDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\GqlException;
use App\Gql\Invocation\CollectAccumulator;
use App\Gql\Invocation\CountAccumulator;
use App\Gql\Invocation\DistinctAccumulator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DistinctAccumulator::class)]
#[UsesClass(CollectAccumulator::class)]
#[UsesClass(CountAccumulator::class)]
#[UsesClass(DatumIdentity::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DatumOrder::class)]
#[UsesClass(DecimalDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(StringDatum::class)]
#[Small]
final class DistinctAccumulatorTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testAcceptSummarisesAValueOfferedTwiceOnlyOnce(): void
    {
        $counter = new DistinctAccumulator(new CountAccumulator());
        $counter->accept(new StringDatum('a'));
        $counter->accept(new StringDatum('a'));

        self::assertEquals(new IntegerDatum(1), $counter->result());
    }

    /**
     * @throws GqlException
     */
    public function testAcceptSummarisesAWholeNumberAndADecimalOfTheSameValueOnlyOnce(): void
    {
        $collected = new DistinctAccumulator(new CollectAccumulator());
        $collected->accept(new IntegerDatum(1));
        $collected->accept(new DecimalDatum(10, 1));

        self::assertEquals(new ListDatum([new IntegerDatum(1)]), $collected->result());
    }

    /**
     * @throws GqlException
     */
    public function testAcceptTellsApartTwoValuesOfDifferentKindsThatReadTheSame(): void
    {
        $collected = new DistinctAccumulator(new CollectAccumulator());
        $collected->accept(new StringDatum('1'));
        $collected->accept(new IntegerDatum(1));

        self::assertEquals(new ListDatum([new StringDatum('1'), new IntegerDatum(1)]), $collected->result());
    }

    /**
     * @throws GqlException
     */
    public function testAcceptOffersTheAbsenceOfAValueToTheSummaryItWraps(): void
    {
        $counter = new DistinctAccumulator(new CountAccumulator(true));
        $counter->accept(new NullDatum());
        $counter->accept(new NullDatum());

        self::assertEquals(new IntegerDatum(1), $counter->result());
    }

    public function testResultAnswersWhateverTheSummaryItWrapsAnswersToNothing(): void
    {
        self::assertEquals(new IntegerDatum(0), (new DistinctAccumulator(new CountAccumulator()))->result());
    }
}
