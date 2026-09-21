<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Invocation;

use App\Gql\Argument\ExactArithmetic;
use App\Gql\Argument\NumberArgument;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DecimalDatum;
use App\Gql\Datum\FloatDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\GqlException;
use App\Gql\Invocation\AverageAccumulator;
use App\Gql\Invocation\SumAccumulator;
use App\Gql\StatusCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AverageAccumulator::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DecimalDatum::class)]
#[UsesClass(ExactArithmetic::class)]
#[UsesClass(FloatDatum::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(NumberArgument::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(SumAccumulator::class)]
#[Small]
final class AverageAccumulatorTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testAcceptPassesOverAValueThatIsNotThereWithoutCountingItAgainstTheAverage(): void
    {
        $average = new AverageAccumulator();
        $average->accept(new IntegerDatum(2));
        $average->accept(new NullDatum());

        self::assertEquals(new DecimalDatum(2000000, 6), $average->result());
    }

    /**
     * @throws GqlException
     */
    public function testAcceptReportsAValueThatIsNotANumber(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G03] error: data exception - invalid value type: a number was expected, and a STRING was given');

        (new AverageAccumulator())->accept(new StringDatum('a'));
    }

    /**
     * @throws GqlException
     */
    public function testResultAveragesWholeNumbersIntoAnExactDecimalWithSixDigitsAfterThePoint(): void
    {
        $average = new AverageAccumulator();
        $average->accept(new IntegerDatum(1));
        $average->accept(new IntegerDatum(2));

        self::assertEquals(new DecimalDatum(1500000, 6), $average->result());
    }

    /**
     * @throws GqlException
     */
    public function testResultKeepsSixDigitsAfterThePointOfAnAverageThatComesOutWhole(): void
    {
        $average = new AverageAccumulator();
        $average->accept(new IntegerDatum(1));
        $average->accept(new IntegerDatum(1));

        self::assertEquals(new DecimalDatum(1000000, 6), $average->result());
    }

    /**
     * @throws GqlException
     */
    public function testResultCutsOffTheDigitsOfAnAverageThatDoNotFit(): void
    {
        $average = new AverageAccumulator();
        $average->accept(new IntegerDatum(1));
        $average->accept(new IntegerDatum(0));
        $average->accept(new IntegerDatum(1));

        self::assertEquals(new DecimalDatum(666666, 6), $average->result());
    }

    /**
     * @throws GqlException
     */
    public function testResultAveragesDecimalsExactly(): void
    {
        $average = new AverageAccumulator();
        $average->accept(new DecimalDatum(15, 1));
        $average->accept(new IntegerDatum(2));

        self::assertEquals(new DecimalDatum(1750000, 6), $average->result());
    }

    /**
     * @throws GqlException
     */
    public function testResultIsApproximateOnceOneValueAveragedIs(): void
    {
        $average = new AverageAccumulator();
        $average->accept(new IntegerDatum(1));
        $average->accept(new FloatDatum(2.0));

        self::assertEquals(new FloatDatum(1.5), $average->result());
    }

    /**
     * @throws GqlException
     */
    public function testResultAnswersNothingWhenNothingWasAveraged(): void
    {
        self::assertEquals(new NullDatum(), (new AverageAccumulator())->result());
    }

    /**
     * @throws GqlException
     */
    public function testResultKeepsFewerDigitsAfterThePointOfAnAverageTooLargeForSix(): void
    {
        $average = new AverageAccumulator();
        $average->accept(new IntegerDatum(10000000000000));

        self::assertEquals(new DecimalDatum(100000000000000000, 4), $average->result());
    }
}
