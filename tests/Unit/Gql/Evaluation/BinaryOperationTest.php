<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Evaluation;

use App\Gql\Argument\ExactArithmetic;
use App\Gql\Argument\NumberArgument;
use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DatumOrder;
use App\Gql\Datum\DecimalDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\Evaluation\Arithmetic;
use App\Gql\Evaluation\BinaryOperation;
use App\Gql\Evaluation\Comparison;
use App\Gql\Evaluation\Logic;
use App\Gql\Evaluation\TextOperation;
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
#[CoversClass(BinaryOperation::class)]
#[UsesClass(Arithmetic::class)]
#[UsesClass(BooleanDatum::class)]
#[UsesClass(Comparison::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DatumOrder::class)]
#[UsesClass(DecimalDatum::class)]
#[UsesClass(ExactArithmetic::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(Logic::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(NumberArgument::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(TextOperation::class)]
#[Small]
final class BinaryOperationTest extends TestCase
{
    /**
     * @throws GqlException
     */
    #[DataProvider('providerOperatorsAndTheRuleTheyFollow')]
    public function testApplySortsEveryOperatorIntoTheRulesItFollows(BinaryOperator $operator, Datum $left, Datum $right, Datum $expected): void
    {
        self::assertEquals($expected, BinaryOperation::apply($operator, $left, $right));
    }

    /**
     * @return iterable<string, array{BinaryOperator, Datum, Datum, Datum}>
     */
    public static function providerOperatorsAndTheRuleTheyFollow(): iterable
    {
        yield 'addition follows the arithmetic rules' => [BinaryOperator::Add, new IntegerDatum(1), new IntegerDatum(2), new IntegerDatum(3)];

        yield 'subtraction follows them too' => [BinaryOperator::Subtract, new IntegerDatum(1), new IntegerDatum(2), new IntegerDatum(-1)];

        yield 'multiplication follows them too' => [BinaryOperator::Multiply, new IntegerDatum(1), new IntegerDatum(2), new IntegerDatum(2)];

        yield 'division follows them too' => [BinaryOperator::Divide, new IntegerDatum(1), new IntegerDatum(2), new IntegerDatum(0)];

        yield 'joining follows the rules about strings' => [BinaryOperator::Concatenate, new StringDatum('a'), new StringDatum('b'), new StringDatum('ab')];

        yield 'equality follows the comparing rules' => [BinaryOperator::Equal, new IntegerDatum(1), new IntegerDatum(2), new BooleanDatum(false)];

        yield 'inequality follows them too' => [BinaryOperator::NotEqual, new IntegerDatum(1), new IntegerDatum(2), new BooleanDatum(true)];

        yield 'ordering follows them too' => [BinaryOperator::Less, new IntegerDatum(1), new IntegerDatum(2), new BooleanDatum(true)];

        yield 'ordering the other way follows them too' => [BinaryOperator::Greater, new IntegerDatum(1), new IntegerDatum(2), new BooleanDatum(false)];

        yield 'ordering with equality follows them too' => [BinaryOperator::LessOrEqual, new IntegerDatum(1), new IntegerDatum(2), new BooleanDatum(true)];

        yield 'and the other way round' => [BinaryOperator::GreaterOrEqual, new IntegerDatum(1), new IntegerDatum(2), new BooleanDatum(false)];

        yield 'conjunction follows three-valued logic' => [BinaryOperator::And, new BooleanDatum(true), new BooleanDatum(false), new BooleanDatum(false)];

        yield 'disjunction follows it too' => [BinaryOperator::Or, new BooleanDatum(true), new BooleanDatum(false), new BooleanDatum(true)];

        yield 'exclusive disjunction follows it too' => [BinaryOperator::Xor, new BooleanDatum(true), new BooleanDatum(false), new BooleanDatum(true)];
    }

    /**
     * @throws GqlException
     */
    public function testApplyLetsAConjunctionBeDecidedByOneFalseSide(): void
    {
        self::assertEquals(new BooleanDatum(false), BinaryOperation::apply(BinaryOperator::And, new BooleanDatum(false), new NullDatum()));
    }

    /**
     * @throws GqlException
     */
    public function testApplyLeavesADisjunctionWithOneFalseSideUndecided(): void
    {
        self::assertEquals(new NullDatum(), BinaryOperation::apply(BinaryOperator::Or, new BooleanDatum(false), new NullDatum()));
    }

    /**
     * @throws GqlException
     */
    public function testApplyReportsAStringJoinedToANumber(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G03] error: data exception - invalid value type');

        BinaryOperation::apply(BinaryOperator::Concatenate, new StringDatum('line '), new IntegerDatum(12));
    }

    /**
     * @throws GqlException
     */
    public function testApplyReportsTwoValuesOfUnrelatedKindsCompared(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G04] error: data exception - values not comparable');

        BinaryOperation::apply(BinaryOperator::Equal, new IntegerDatum(5), new StringDatum('5'));
    }

    /**
     * @throws GqlException
     */
    public function testApplyReportsAConnectiveGivenSomethingThatIsNotATruthValue(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G03] error: data exception - invalid value type: a truth value was expected, and a INT64 was given');

        BinaryOperation::apply(BinaryOperator::And, new IntegerDatum(1), new BooleanDatum(true));
    }
}
