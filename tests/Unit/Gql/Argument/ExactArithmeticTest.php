<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Argument;

use App\Gql\Argument\ExactArithmetic;
use App\Gql\Datum\DecimalDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\GqlException;
use App\Gql\StatusCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ExactArithmetic::class)]
#[UsesClass(DecimalDatum::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(StatusCode::class)]
#[Small]
final class ExactArithmeticTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testAddAddsTwoDecimalsExactly(): void
    {
        self::assertEquals(new DecimalDatum(3, 1), ExactArithmetic::add(new DecimalDatum(1, 1), new DecimalDatum(2, 1)));
    }

    /**
     * @throws GqlException
     */
    public function testAddKeepsTheLargerOfTheTwoScales(): void
    {
        self::assertEquals(new DecimalDatum(175, 2), ExactArithmetic::add(new DecimalDatum(15, 1), new DecimalDatum(25, 2)));
    }

    /**
     * @throws GqlException
     */
    public function testAddKeepsTheScaleOfASumThatComesOutWhole(): void
    {
        self::assertEquals(new DecimalDatum(10, 1), ExactArithmetic::add(new DecimalDatum(5, 1), new DecimalDatum(5, 1)));
    }

    /**
     * @throws GqlException
     */
    public function testAddAddsTwoWholeNumbersUpToAWholeNumber(): void
    {
        self::assertEquals(new IntegerDatum(3), ExactArithmetic::add(new IntegerDatum(1), new IntegerDatum(2)));
    }

    /**
     * @throws GqlException
     */
    public function testAddReportsASumTooLargeToHold(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22003] error: data exception - numeric value out of range: the result has more digits than an exact number can hold');

        ExactArithmetic::add(new IntegerDatum(PHP_INT_MAX), new IntegerDatum(1));
    }

    /**
     * @throws GqlException
     */
    public function testSubtractSubtractsADecimalFromAWholeNumberExactly(): void
    {
        self::assertEquals(new DecimalDatum(75, 2), ExactArithmetic::subtract(new IntegerDatum(1), new DecimalDatum(25, 2)));
    }

    /**
     * @throws GqlException
     */
    public function testSubtractGoesBelowZero(): void
    {
        self::assertEquals(new DecimalDatum(-2, 1), ExactArithmetic::subtract(new DecimalDatum(1, 1), new DecimalDatum(3, 1)));
    }

    /**
     * @throws GqlException
     */
    public function testSubtractReportsADifferenceTooLargeToHold(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22003] error: data exception - numeric value out of range');

        ExactArithmetic::subtract(new IntegerDatum(-PHP_INT_MAX), new IntegerDatum(2));
    }

    /**
     * @throws GqlException
     */
    public function testMultiplyAddsTheScalesOfItsFactors(): void
    {
        self::assertEquals(new DecimalDatum(30, 2), ExactArithmetic::multiply(new DecimalDatum(15, 1), new DecimalDatum(2, 1)));
    }

    /**
     * @throws GqlException
     */
    public function testMultiplyMultipliesTwoWholeNumbersIntoAWholeNumber(): void
    {
        self::assertEquals(new IntegerDatum(6), ExactArithmetic::multiply(new IntegerDatum(2), new IntegerDatum(3)));
    }

    /**
     * @throws GqlException
     */
    public function testMultiplyReportsAProductTooLargeToHold(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22003] error: data exception - numeric value out of range');

        ExactArithmetic::multiply(new IntegerDatum(PHP_INT_MAX), new IntegerDatum(2));
    }

    /**
     * @throws GqlException
     */
    public function testDivideDividesTwoWholeNumbersIntoAWholeNumber(): void
    {
        self::assertEquals(new IntegerDatum(3), ExactArithmetic::divide(new IntegerDatum(7), new IntegerDatum(2)));
    }

    /**
     * @throws GqlException
     */
    public function testDivideCutsAWholeQuotientOffTowardsZero(): void
    {
        self::assertEquals(new IntegerDatum(-3), ExactArithmetic::divide(new IntegerDatum(-7), new IntegerDatum(2)));
    }

    /**
     * @throws GqlException
     */
    public function testDivideKeepsSixDigitsAfterThePointWhenTheNumberDividedIsADecimal(): void
    {
        self::assertEquals(new DecimalDatum(3500000, 6), ExactArithmetic::divide(new DecimalDatum(70, 1), new IntegerDatum(2)));
    }

    /**
     * @throws GqlException
     */
    public function testDivideKeepsSixDigitsAfterThePointWhenTheDivisorIsADecimal(): void
    {
        self::assertEquals(new DecimalDatum(333333, 6), ExactArithmetic::divide(new IntegerDatum(1), new DecimalDatum(30, 1)));
    }

    /**
     * @throws GqlException
     */
    public function testDivideCutsOffTheDigitsThatDoNotFitRatherThanRoundingThem(): void
    {
        self::assertEquals(new DecimalDatum(666666, 6), ExactArithmetic::divide(new DecimalDatum(20, 1), new IntegerDatum(3)));
    }

    /**
     * @throws GqlException
     */
    public function testDivideKeepsTheScaleOfADecimalWithMoreThanSixDigitsAfterItsPoint(): void
    {
        self::assertEquals(new DecimalDatum(1, 8), ExactArithmetic::divide(new DecimalDatum(1, 8), new IntegerDatum(1)));
    }

    /**
     * @throws GqlException
     */
    public function testDivideKeepsSixDigitsForTwoWholeNumbersWhenAskedTo(): void
    {
        self::assertEquals(new DecimalDatum(500000, 6), ExactArithmetic::divide(new IntegerDatum(1), new IntegerDatum(2), true));
    }

    /**
     * @throws GqlException
     */
    public function testDivideReportsADivisionByZero(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22012] error: data exception - division by zero: a number cannot be divided by zero');

        ExactArithmetic::divide(new IntegerDatum(1), new IntegerDatum(0));
    }

    /**
     * @throws GqlException
     */
    public function testDivideReportsADivisionByAZeroWrittenWithDigitsAfterItsPoint(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22012] error: data exception - division by zero');

        ExactArithmetic::divide(new IntegerDatum(1), new DecimalDatum(0, 1));
    }

    /**
     * @throws GqlException
     */
    public function testDivideReportsAQuotientTooLargeToKeepItsDigitsAfterThePoint(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22003] error: data exception - numeric value out of range');

        ExactArithmetic::divide(new IntegerDatum(PHP_INT_MAX), new DecimalDatum(1, 1));
    }

    /**
     * @throws GqlException
     */
    public function testScaledReturnsADecimalsDigitsAtALargerScale(): void
    {
        self::assertSame(1500, ExactArithmetic::scaled(new DecimalDatum(15, 1), 3));
    }

    /**
     * @throws GqlException
     */
    public function testScaledReturnsAWholeNumbersDigitsAtAScale(): void
    {
        self::assertSame(200, ExactArithmetic::scaled(new IntegerDatum(2), 2));
    }

    /**
     * @throws GqlException
     */
    public function testScaledReportsDigitsTooManyToHold(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22003] error: data exception - numeric value out of range');

        ExactArithmetic::scaled(new IntegerDatum(PHP_INT_MAX), 1);
    }

    /**
     * @throws GqlException
     */
    public function testPowerReturnsAPowerOfTen(): void
    {
        self::assertSame(1000, ExactArithmetic::power(3));
    }

    /**
     * @throws GqlException
     */
    public function testPowerOfNothingIsOne(): void
    {
        self::assertSame(1, ExactArithmetic::power(0));
    }

    /**
     * @throws GqlException
     */
    public function testPowerReportsThePowerOfTenTooLargeToHold(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22003] error: data exception - numeric value out of range');

        ExactArithmetic::power(19);
    }

    /**
     * @throws GqlException
     */
    public function testCheckedReturnsAResultThatIsStillAWholeNumber(): void
    {
        self::assertSame(42, ExactArithmetic::checked(42));
    }

    /**
     * @throws GqlException
     */
    public function testCheckedReportsAResultThatOverflowedIntoAFloat(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22003] error: data exception - numeric value out of range');

        ExactArithmetic::checked(1.0e30);
    }

    public function testScaleOfAWholeNumberIsNothing(): void
    {
        self::assertSame(0, ExactArithmetic::scaleOf(new IntegerDatum(3)));
    }

    public function testScaleOfADecimalIsHowManyDigitsComeAfterItsPoint(): void
    {
        self::assertSame(2, ExactArithmetic::scaleOf(new DecimalDatum(150, 2)));
    }

    public function testUnscaledOfAWholeNumberIsItsOwnDigits(): void
    {
        self::assertSame(3, ExactArithmetic::unscaledOf(new IntegerDatum(3)));
    }

    public function testUnscaledOfADecimalIsItsDigitsWithoutThePoint(): void
    {
        self::assertSame(150, ExactArithmetic::unscaledOf(new DecimalDatum(150, 2)));
    }

    public function testRoomLeavesANumberOfFourteenDigitsRoomForFourMore(): void
    {
        self::assertSame(4, ExactArithmetic::room(10000000000000));
    }

    public function testRoomLeavesNothingRoomForEighteen(): void
    {
        self::assertSame(18, ExactArithmetic::room(0));
    }

    public function testRoomCountsTheDigitsOfANegativeNumberWithoutItsSign(): void
    {
        self::assertSame(0, ExactArithmetic::room(PHP_INT_MIN));
    }

    /**
     * @throws GqlException
     */
    public function testDivideKeepsFewerDigitsAfterThePointRatherThanNoneAtAll(): void
    {
        self::assertEquals(new DecimalDatum(100000000000000000, 4), ExactArithmetic::divide(new IntegerDatum(10000000000000), new IntegerDatum(1), true));
    }

    /**
     * @throws GqlException
     */
    public function testDivideReportsTheOneQuotientOfTwoWholeNumbersThatDoesNotFit(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('22003');

        ExactArithmetic::divide(new IntegerDatum(PHP_INT_MIN), new IntegerDatum(-1));
    }
}
