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
use App\Gql\Invocation\SumAccumulator;
use App\Gql\StatusCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SumAccumulator::class)]
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
#[Small]
final class SumAccumulatorTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testAcceptAddsWholeNumbersUpToAWholeNumber(): void
    {
        $sum = new SumAccumulator();
        $sum->accept(new IntegerDatum(2));
        $sum->accept(new IntegerDatum(3));

        self::assertEquals(new IntegerDatum(5), $sum->result());
    }

    /**
     * @throws GqlException
     */
    public function testAcceptAddsDecimalsUpExactly(): void
    {
        $sum = new SumAccumulator();
        $sum->accept(new DecimalDatum(1, 1));
        $sum->accept(new DecimalDatum(2, 1));

        self::assertEquals(new DecimalDatum(3, 1), $sum->result());
    }

    /**
     * @throws GqlException
     */
    public function testAcceptAddsAWholeNumberAndADecimalUpToADecimal(): void
    {
        $sum = new SumAccumulator();
        $sum->accept(new IntegerDatum(1));
        $sum->accept(new DecimalDatum(15, 1));

        self::assertEquals(new DecimalDatum(25, 1), $sum->result());
    }

    /**
     * @throws GqlException
     */
    public function testAcceptMakesTheWholeSumApproximateOnceOneValueIs(): void
    {
        $sum = new SumAccumulator();
        $sum->accept(new IntegerDatum(2));
        $sum->accept(new FloatDatum(0.5));

        self::assertEquals(new FloatDatum(2.5), $sum->result());
    }

    /**
     * @throws GqlException
     */
    public function testAcceptKeepsTheSumApproximateWhenExactValuesFollowAnApproximateOne(): void
    {
        $sum = new SumAccumulator();
        $sum->accept(new FloatDatum(0.5));
        $sum->accept(new IntegerDatum(1));
        $sum->accept(new DecimalDatum(1, 1));

        self::assertEquals(new FloatDatum(1.6), $sum->result());
    }

    /**
     * @throws GqlException
     */
    public function testAcceptPassesOverAValueThatIsNotThere(): void
    {
        $sum = new SumAccumulator();
        $sum->accept(new NullDatum());
        $sum->accept(new IntegerDatum(2));

        self::assertEquals(new IntegerDatum(2), $sum->result());
    }

    /**
     * @throws GqlException
     */
    public function testAcceptReportsAValueThatIsNotANumber(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G03] error: data exception - invalid value type: a number was expected, and a STRING was given');

        (new SumAccumulator())->accept(new StringDatum('a'));
    }

    /**
     * @throws GqlException
     */
    public function testAcceptReportsATotalTooLargeToHoldRatherThanApproximatingIt(): void
    {
        $sum = new SumAccumulator();
        $sum->accept(new IntegerDatum(PHP_INT_MAX));

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22003] error: data exception - numeric value out of range');

        $sum->accept(new IntegerDatum(1));
    }

    public function testResultAnswersNothingRatherThanZeroWhenNothingWasAddedUp(): void
    {
        self::assertEquals(new NullDatum(), (new SumAccumulator())->result());
    }

    /**
     * @throws GqlException
     */
    public function testResultAnswersNothingWhenOnlyAbsentValuesWereOffered(): void
    {
        $sum = new SumAccumulator();
        $sum->accept(new NullDatum());

        self::assertEquals(new NullDatum(), $sum->result());
    }
}
