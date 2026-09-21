<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Invocation;

use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DatumOrder;
use App\Gql\Datum\DecimalDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\GqlException;
use App\Gql\Invocation\ExtremeAccumulator;
use App\Gql\StatusCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ExtremeAccumulator::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DatumOrder::class)]
#[UsesClass(DecimalDatum::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(StringDatum::class)]
#[Small]
final class ExtremeAccumulatorTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testAcceptKeepsTheSmallestOfWhatItWasOffered(): void
    {
        $least = new ExtremeAccumulator();
        $least->accept(new IntegerDatum(3));
        $least->accept(new IntegerDatum(1));
        $least->accept(new IntegerDatum(2));

        self::assertEquals(new IntegerDatum(1), $least->result());
    }

    /**
     * @throws GqlException
     */
    public function testAcceptKeepsTheLargestWhenThatIsWhatWasAskedFor(): void
    {
        $most = new ExtremeAccumulator(true);
        $most->accept(new IntegerDatum(1));
        $most->accept(new IntegerDatum(3));
        $most->accept(new IntegerDatum(2));

        self::assertEquals(new IntegerDatum(3), $most->result());
    }

    /**
     * @throws GqlException
     */
    public function testAcceptOrdersNumbersOfDifferentKindsByValue(): void
    {
        $least = new ExtremeAccumulator();
        $least->accept(new IntegerDatum(3));
        $least->accept(new DecimalDatum(25, 1));

        self::assertEquals(new DecimalDatum(25, 1), $least->result());
    }

    /**
     * @throws GqlException
     */
    public function testAcceptKeepsTheFirstOfTwoEqualValues(): void
    {
        $least = new ExtremeAccumulator();
        $least->accept(new IntegerDatum(2));
        $least->accept(new DecimalDatum(20, 1));

        self::assertEquals(new IntegerDatum(2), $least->result());
    }

    /**
     * @throws GqlException
     */
    public function testAcceptPassesOverAValueThatIsNotThere(): void
    {
        $least = new ExtremeAccumulator();
        $least->accept(new NullDatum());
        $least->accept(new IntegerDatum(3));
        $least->accept(new NullDatum());

        self::assertEquals(new IntegerDatum(3), $least->result());
    }

    /**
     * @throws GqlException
     */
    public function testAcceptReportsValuesOfKindsWithNoOrderBetweenThem(): void
    {
        $least = new ExtremeAccumulator();
        $least->accept(new StringDatum('a'));

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G04] error: data exception - values not comparable: INT64 and STRING cannot be compared');

        $least->accept(new IntegerDatum(3));
    }

    public function testResultAnswersNothingWhenNothingWasOffered(): void
    {
        self::assertEquals(new NullDatum(), (new ExtremeAccumulator())->result());
    }
}
