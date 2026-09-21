<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Evaluation;

use App\Gql\Argument\ExactArithmetic;
use App\Gql\Argument\NumberArgument;
use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DecimalDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\Evaluation\Arithmetic;
use App\Gql\Evaluation\Logic;
use App\Gql\Evaluation\UnaryOperation;
use App\Gql\GqlException;
use App\Gql\StatusCode;
use App\Gql\Syntax\Expression\UnaryOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(UnaryOperation::class)]
#[UsesClass(Arithmetic::class)]
#[UsesClass(BooleanDatum::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DecimalDatum::class)]
#[UsesClass(ExactArithmetic::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(Logic::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(NumberArgument::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(StringDatum::class)]
#[Small]
final class UnaryOperationTest extends TestCase
{
    /**
     * @throws GqlException
     */
    #[DataProvider('providerOperatorsAndTheRuleTheyFollow')]
    public function testApplySortsEveryOperatorIntoTheRulesItFollows(UnaryOperator $operator, Datum $operand, Datum $expected): void
    {
        self::assertEquals($expected, UnaryOperation::apply($operator, $operand));
    }

    /**
     * @return iterable<string, array{UnaryOperator, Datum, Datum}>
     */
    public static function providerOperatorsAndTheRuleTheyFollow(): iterable
    {
        yield 'a sign is arithmetic' => [UnaryOperator::Negate, new IntegerDatum(3), new IntegerDatum(-3)];

        yield 'a sign keeps a decimal exact' => [UnaryOperator::Negate, new DecimalDatum(15, 1), new DecimalDatum(-15, 1)];

        yield 'a positive sign says the value is meant to be a number' => [UnaryOperator::Identity, new IntegerDatum(3), new IntegerDatum(3)];

        yield 'a negation follows three-valued logic' => [UnaryOperator::Not, new BooleanDatum(true), new BooleanDatum(false)];

        yield 'and leaves the undecided undecided' => [UnaryOperator::Not, new NullDatum(), new NullDatum()];

        yield 'a null test of a value' => [UnaryOperator::IsNull, new IntegerDatum(3), new BooleanDatum(false)];

        yield 'a null test of the absence of one' => [UnaryOperator::IsNull, new NullDatum(), new BooleanDatum(true)];

        yield 'the opposite null test of a value' => [UnaryOperator::IsNotNull, new IntegerDatum(3), new BooleanDatum(true)];

        yield 'the opposite null test of the absence of one' => [UnaryOperator::IsNotNull, new NullDatum(), new BooleanDatum(false)];
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerTruthTestsAndTheirAnswers')]
    public function testApplyAnswersATruthTestDefinitelyEvenOfAnUndecidedValue(UnaryOperator $operator, Datum $operand, BooleanDatum $expected): void
    {
        self::assertEquals($expected, UnaryOperation::apply($operator, $operand));
    }

    /**
     * @return iterable<string, array{UnaryOperator, Datum, BooleanDatum}>
     */
    public static function providerTruthTestsAndTheirAnswers(): iterable
    {
        yield 'TRUE IS TRUE' => [UnaryOperator::IsTrue, new BooleanDatum(true), new BooleanDatum(true)];

        yield 'FALSE IS TRUE' => [UnaryOperator::IsTrue, new BooleanDatum(false), new BooleanDatum(false)];

        yield 'UNKNOWN IS TRUE' => [UnaryOperator::IsTrue, new NullDatum(), new BooleanDatum(false)];

        yield 'TRUE IS NOT TRUE' => [UnaryOperator::IsNotTrue, new BooleanDatum(true), new BooleanDatum(false)];

        yield 'FALSE IS NOT TRUE' => [UnaryOperator::IsNotTrue, new BooleanDatum(false), new BooleanDatum(true)];

        yield 'UNKNOWN IS NOT TRUE' => [UnaryOperator::IsNotTrue, new NullDatum(), new BooleanDatum(true)];

        yield 'TRUE IS FALSE' => [UnaryOperator::IsFalse, new BooleanDatum(true), new BooleanDatum(false)];

        yield 'FALSE IS FALSE' => [UnaryOperator::IsFalse, new BooleanDatum(false), new BooleanDatum(true)];

        yield 'UNKNOWN IS FALSE' => [UnaryOperator::IsFalse, new NullDatum(), new BooleanDatum(false)];

        yield 'TRUE IS NOT FALSE' => [UnaryOperator::IsNotFalse, new BooleanDatum(true), new BooleanDatum(true)];

        yield 'FALSE IS NOT FALSE' => [UnaryOperator::IsNotFalse, new BooleanDatum(false), new BooleanDatum(false)];

        yield 'UNKNOWN IS NOT FALSE' => [UnaryOperator::IsNotFalse, new NullDatum(), new BooleanDatum(true)];

        yield 'TRUE IS UNKNOWN' => [UnaryOperator::IsUnknown, new BooleanDatum(true), new BooleanDatum(false)];

        yield 'FALSE IS UNKNOWN' => [UnaryOperator::IsUnknown, new BooleanDatum(false), new BooleanDatum(false)];

        yield 'UNKNOWN IS UNKNOWN' => [UnaryOperator::IsUnknown, new NullDatum(), new BooleanDatum(true)];

        yield 'TRUE IS NOT UNKNOWN' => [UnaryOperator::IsNotUnknown, new BooleanDatum(true), new BooleanDatum(true)];

        yield 'FALSE IS NOT UNKNOWN' => [UnaryOperator::IsNotUnknown, new BooleanDatum(false), new BooleanDatum(true)];

        yield 'UNKNOWN IS NOT UNKNOWN' => [UnaryOperator::IsNotUnknown, new NullDatum(), new BooleanDatum(false)];
    }

    /**
     * @throws GqlException
     */
    public function testApplyReportsATruthTestOfSomethingThatIsNotATruthValue(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G03] error: data exception - invalid value type: a truth value was expected, and a INT64 was given');

        UnaryOperation::apply(UnaryOperator::IsTrue, new IntegerDatum(1));
    }

    /**
     * @throws GqlException
     */
    public function testIdentityReturnsTheNumberItWasWrittenBefore(): void
    {
        self::assertEquals(new DecimalDatum(15, 1), UnaryOperation::identity(new DecimalDatum(15, 1)));
    }

    /**
     * @throws GqlException
     */
    public function testIdentityLeavesTheAbsenceOfAValueAlone(): void
    {
        self::assertEquals(new NullDatum(), UnaryOperation::identity(new NullDatum()));
    }

    /**
     * @throws GqlException
     */
    public function testIdentityReportsSomethingThatIsNotANumber(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G03] error: data exception - invalid value type: a number was expected, and a STRING was given');

        UnaryOperation::identity(new StringDatum('3'));
    }
}
