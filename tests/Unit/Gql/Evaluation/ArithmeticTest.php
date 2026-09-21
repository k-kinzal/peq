<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Evaluation;

use App\Gql\Argument\ExactArithmetic;
use App\Gql\Argument\NumberArgument;
use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DecimalDatum;
use App\Gql\Datum\FloatDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\Evaluation\Arithmetic;
use App\Gql\GqlException;
use App\Gql\StatusCode;
use App\Gql\Syntax\Expression\BinaryOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Arithmetic::class)]
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
final class ArithmeticTest extends TestCase
{
    /**
     * @throws GqlException
     */
    #[DataProvider('providerOperationsAndWhatTheyProduce')]
    public function testApplyFollowsTheRulesAboutMixingKindsOfNumber(BinaryOperator $operator, Datum $left, Datum $right, Datum $expected): void
    {
        self::assertEquals($expected, Arithmetic::apply($operator, $left, $right));
    }

    /**
     * @return iterable<string, array{BinaryOperator, Datum, Datum, Datum}>
     */
    public static function providerOperationsAndWhatTheyProduce(): iterable
    {
        yield 'whole numbers added stay whole' => [BinaryOperator::Add, new IntegerDatum(1), new IntegerDatum(2), new IntegerDatum(3)];

        yield 'whole numbers subtracted stay whole' => [BinaryOperator::Subtract, new IntegerDatum(5), new IntegerDatum(2), new IntegerDatum(3)];

        yield 'whole numbers multiplied stay whole' => [BinaryOperator::Multiply, new IntegerDatum(2), new IntegerDatum(3), new IntegerDatum(6)];

        yield 'whole numbers divided stay whole' => [BinaryOperator::Divide, new IntegerDatum(7), new IntegerDatum(2), new IntegerDatum(3)];

        yield 'two decimals add up exactly' => [BinaryOperator::Add, new DecimalDatum(1, 1), new DecimalDatum(2, 1), new DecimalDatum(3, 1)];

        yield 'a whole number and a decimal add up to a decimal' => [BinaryOperator::Add, new IntegerDatum(1), new DecimalDatum(15, 1), new DecimalDatum(25, 1)];

        yield 'a decimal divided keeps six digits after the point' => [BinaryOperator::Divide, new DecimalDatum(70, 1), new IntegerDatum(2), new DecimalDatum(3500000, 6)];

        yield 'meeting an approximate number makes the sum approximate' => [BinaryOperator::Add, new IntegerDatum(1), new FloatDatum(1.5), new FloatDatum(2.5)];

        yield 'meeting one makes the difference approximate' => [BinaryOperator::Subtract, new FloatDatum(2.5), new IntegerDatum(1), new FloatDatum(1.5)];

        yield 'meeting one makes the product of a decimal approximate' => [BinaryOperator::Multiply, new DecimalDatum(15, 1), new FloatDatum(2.0), new FloatDatum(3.0)];

        yield 'an approximate division keeps its fraction' => [BinaryOperator::Divide, new IntegerDatum(3), new FloatDatum(2.0), new FloatDatum(1.5)];

        yield 'meeting the absence of a value produces the absence of one' => [BinaryOperator::Add, new IntegerDatum(1), new NullDatum(), new NullDatum()];

        yield 'the absence of a value on the left too' => [BinaryOperator::Multiply, new NullDatum(), new IntegerDatum(2), new NullDatum()];
    }

    /**
     * @throws GqlException
     */
    public function testApplyReportsAValueThatIsNotANumber(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G03] error: data exception - invalid value type: a number was expected, and a STRING was given');

        Arithmetic::apply(BinaryOperator::Add, new IntegerDatum(1), new StringDatum('a'));
    }

    /**
     * @throws GqlException
     */
    public function testApplyReportsAWholeNumberDividedByZero(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22012] error: data exception - division by zero');

        Arithmetic::apply(BinaryOperator::Divide, new IntegerDatum(1), new IntegerDatum(0));
    }

    /**
     * @throws GqlException
     */
    public function testApplyReportsAnApproximateNumberDividedByZero(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22012] error: data exception - division by zero');

        Arithmetic::apply(BinaryOperator::Divide, new FloatDatum(1.0), new IntegerDatum(0));
    }

    /**
     * @throws GqlException
     */
    public function testApplyReportsAnExactResultTooLargeToHoldRatherThanApproximatingIt(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22003] error: data exception - numeric value out of range');

        Arithmetic::apply(BinaryOperator::Multiply, new IntegerDatum(PHP_INT_MAX), new IntegerDatum(2));
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerApproximateOperations')]
    public function testApproximateAppliesTheOperatorToTwoApproximateNumbers(BinaryOperator $operator, float $left, float $right, FloatDatum $expected): void
    {
        self::assertEquals($expected, Arithmetic::approximate($operator, $left, $right));
    }

    /**
     * @return iterable<string, array{BinaryOperator, float, float, FloatDatum}>
     */
    public static function providerApproximateOperations(): iterable
    {
        yield 'a sum' => [BinaryOperator::Add, 1.5, 1.0, new FloatDatum(2.5)];

        yield 'a difference' => [BinaryOperator::Subtract, 1.5, 1.0, new FloatDatum(0.5)];

        yield 'a product' => [BinaryOperator::Multiply, 1.5, 2.0, new FloatDatum(3.0)];

        yield 'a quotient, which keeps its fraction' => [BinaryOperator::Divide, 7.0, 2.0, new FloatDatum(3.5)];
    }

    /**
     * @throws GqlException
     */
    public function testApproximateReportsADivisionByZeroRatherThanAnInfinity(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22012] error: data exception - division by zero: a number cannot be divided by zero');

        Arithmetic::approximate(BinaryOperator::Divide, 1.0, 0.0);
    }

    /**
     * @throws GqlException
     */
    public function testNegateKeepsAWholeNumberWhole(): void
    {
        self::assertEquals(new IntegerDatum(-3), Arithmetic::negate(new IntegerDatum(3)));
    }

    /**
     * @throws GqlException
     */
    public function testNegateKeepsADecimalExact(): void
    {
        self::assertEquals(new DecimalDatum(-15, 1), Arithmetic::negate(new DecimalDatum(15, 1)));
    }

    /**
     * @throws GqlException
     */
    public function testNegateKeepsAnApproximateNumberApproximate(): void
    {
        self::assertEquals(new FloatDatum(-1.5), Arithmetic::negate(new FloatDatum(1.5)));
    }

    /**
     * @throws GqlException
     */
    public function testNegateFindsNoSignToReverseOnTheAbsenceOfAValue(): void
    {
        self::assertEquals(new NullDatum(), Arithmetic::negate(new NullDatum()));
    }

    /**
     * @throws GqlException
     */
    public function testNegateReportsAValueThatIsNotANumber(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G03] error: data exception - invalid value type');

        Arithmetic::negate(new StringDatum('3'));
    }

    /**
     * @throws GqlException
     */
    public function testNegateReportsTheOneWholeNumberWhoseOppositeDoesNotFit(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22003] error: data exception - numeric value out of range');

        Arithmetic::negate(new IntegerDatum(PHP_INT_MIN));
    }
}
