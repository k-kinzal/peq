<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Invocation;

use App\Gql\Datum\DatumKind;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\GqlException;
use App\Gql\Invocation\CountAccumulator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CountAccumulator::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(StringDatum::class)]
#[Small]
final class CountAccumulatorTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testAcceptCountsEveryValueThatIsThereOfWhateverKind(): void
    {
        $counter = new CountAccumulator();
        $counter->accept(new IntegerDatum(1));
        $counter->accept(new IntegerDatum(1));
        $counter->accept(new StringDatum('a'));

        self::assertEquals(new IntegerDatum(3), $counter->result());
    }

    /**
     * @throws GqlException
     */
    public function testAcceptPassesOverAValueThatIsNotThere(): void
    {
        $counter = new CountAccumulator();
        $counter->accept(new NullDatum());
        $counter->accept(new IntegerDatum(1));

        self::assertEquals(new IntegerDatum(1), $counter->result());
    }

    /**
     * @throws GqlException
     */
    public function testAcceptCountsARowWhateverItCarriesWhenCountingRows(): void
    {
        $counter = new CountAccumulator(true);
        $counter->accept(new NullDatum());
        $counter->accept(new IntegerDatum(1));

        self::assertEquals(new IntegerDatum(2), $counter->result());
    }

    public function testResultAnswersZeroRatherThanNothingWhenNothingWasCounted(): void
    {
        self::assertEquals(new IntegerDatum(0), (new CountAccumulator())->result());
    }
}
