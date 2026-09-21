<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Evaluation;

use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DatumOrder;
use App\Gql\Datum\DecimalDatum;
use App\Gql\Datum\FloatDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\Evaluation\Comparison;
use App\Gql\Evaluation\Logic;
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
#[CoversClass(Comparison::class)]
#[UsesClass(BooleanDatum::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DatumOrder::class)]
#[UsesClass(DecimalDatum::class)]
#[UsesClass(FloatDatum::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(Logic::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(StringDatum::class)]
#[Small]
final class ComparisonTest extends TestCase
{
    /**
     * @throws GqlException
     */
    #[DataProvider('providerComparisons')]
    public function testApplyAsksTheQuestionTheOperatorNames(BinaryOperator $operator, Datum $left, Datum $right, Datum $expected): void
    {
        self::assertEquals($expected, Comparison::apply($operator, $left, $right));
    }

    /**
     * @return iterable<string, array{BinaryOperator, Datum, Datum, Datum}>
     */
    public static function providerComparisons(): iterable
    {
        yield 'one number less than another' => [BinaryOperator::Less, new IntegerDatum(1), new IntegerDatum(2), new BooleanDatum(true)];

        yield 'one number no greater than another' => [BinaryOperator::LessOrEqual, new IntegerDatum(2), new IntegerDatum(2), new BooleanDatum(true)];

        yield 'one number greater than another' => [BinaryOperator::Greater, new IntegerDatum(2), new IntegerDatum(3), new BooleanDatum(false)];

        yield 'one number no less than another' => [BinaryOperator::GreaterOrEqual, new IntegerDatum(2), new IntegerDatum(3), new BooleanDatum(false)];

        yield 'two equal numbers' => [BinaryOperator::Equal, new IntegerDatum(2), new IntegerDatum(2), new BooleanDatum(true)];

        yield 'two unequal numbers' => [BinaryOperator::NotEqual, new IntegerDatum(2), new IntegerDatum(3), new BooleanDatum(true)];

        yield 'a whole number against an approximate one' => [BinaryOperator::Less, new IntegerDatum(1), new FloatDatum(1.5), new BooleanDatum(true)];

        yield 'a whole number against a decimal of the same value' => [BinaryOperator::Equal, new IntegerDatum(1), new DecimalDatum(10, 1), new BooleanDatum(true)];

        yield 'two decimals written to different scales' => [BinaryOperator::GreaterOrEqual, new DecimalDatum(15, 1), new DecimalDatum(150, 2), new BooleanDatum(true)];

        yield 'two strings in alphabetical order' => [BinaryOperator::Less, new StringDatum('a'), new StringDatum('b'), new BooleanDatum(true)];

        yield 'two references to the same symbol' => [BinaryOperator::Equal, new NodeDatum('a', ['Class']), new NodeDatum('a'), new BooleanDatum(true)];

        yield 'nothing equals the absence of a value' => [BinaryOperator::Equal, new NullDatum(), new NullDatum(), new NullDatum()];

        yield 'nothing is unequal to it either' => [BinaryOperator::NotEqual, new IntegerDatum(1), new NullDatum(), new NullDatum()];

        yield 'nothing orders against the absence of a value' => [BinaryOperator::Less, new IntegerDatum(1), new NullDatum(), new NullDatum()];

        yield 'two lists that differ in an absent value' => [
            BinaryOperator::Equal,
            new ListDatum([new IntegerDatum(1), new NullDatum()]),
            new ListDatum([new IntegerDatum(1), new IntegerDatum(2)]),
            new NullDatum(),
        ];
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerComparisonsOfValuesWithNoOrderBetweenThem')]
    public function testApplyReportsTwoValuesWithNoOrderBetweenThemAsNotComparable(BinaryOperator $operator, Datum $left, Datum $right): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G04] error: data exception - values not comparable');

        Comparison::apply($operator, $left, $right);
    }

    /**
     * @return iterable<string, array{BinaryOperator, Datum, Datum}>
     */
    public static function providerComparisonsOfValuesWithNoOrderBetweenThem(): iterable
    {
        yield 'a number equal to the string spelling it' => [BinaryOperator::Equal, new IntegerDatum(5), new StringDatum('5')];

        yield 'a number unequal to the string spelling it' => [BinaryOperator::NotEqual, new IntegerDatum(5), new StringDatum('5')];

        yield 'a number ordered against the string spelling it' => [BinaryOperator::Less, new IntegerDatum(5), new StringDatum('5')];

        yield 'one symbol ordered against another' => [BinaryOperator::GreaterOrEqual, new NodeDatum('a'), new NodeDatum('b')];
    }
}
