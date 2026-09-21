<?php

declare(strict_types=1);

namespace App\Gql\Evaluation;

use App\Gql\Argument\ExactArithmetic;
use App\Gql\Argument\NumberArgument;
use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\FloatDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\GqlException;
use App\Gql\StatusCode;
use App\Gql\Syntax\Expression\BinaryOperator;
use InvalidArgumentException;

/**
 * Arithmetic, under GQL's rules about what mixing kinds of number produces.
 *
 * Three rules decide everything here, and all three are the standard's. An expression
 * on two exact numbers produces an exact one — `0.1 + 0.2` is `0.3` — which
 * `ExactArithmetic` works out. An expression that meets an approximate number produces
 * an approximate one, so `1 + 1.5e0` is a float. And an expression that meets the
 * absence of a value produces the absence of one, so a sum over a property some
 * symbols do not carry is absent rather than wrong.
 *
 * Division by zero is reported rather than turned into infinity the way PHP's own
 * division would, and an exact result too large to hold is reported rather than turned
 * into a float.
 *
 * @visibility App\Gql
 */
final class Arithmetic
{
    /**
     * Applies an arithmetic operator to two values.
     *
     * @param BinaryOperator $operator What to apply
     * @param Datum          $left     The value on its left
     * @param Datum          $right    The value on its right
     *
     * @example Two exact numbers make an exact one
     *     \App\Gql\Evaluation\Arithmetic::apply(\App\Gql\Syntax\Expression\BinaryOperator::Add, new \App\Gql\Datum\DecimalDatum(1, 1), new \App\Gql\Datum\DecimalDatum(2, 1))->toText() // => '0.3'
     * @example An approximate number makes the result approximate
     *     \App\Gql\Evaluation\Arithmetic::apply(\App\Gql\Syntax\Expression\BinaryOperator::Add, new \App\Gql\Datum\IntegerDatum(1), new \App\Gql\Datum\FloatDatum(1.5))->kind() // => \App\Gql\Datum\DatumKind::Float
     * @example An absent value makes the result absent
     *     \App\Gql\Evaluation\Arithmetic::apply(\App\Gql\Syntax\Expression\BinaryOperator::Add, new \App\Gql\Datum\IntegerDatum(1), new \App\Gql\Datum\NullDatum())->kind() // => \App\Gql\Datum\DatumKind::Null
     *
     * @return Datum What the operator produced
     *
     * @throws GqlException             If a value is not a number, the divisor is zero, or an exact result is out of range
     * @throws InvalidArgumentException If the operator is not an arithmetic one
     */
    public static function apply(BinaryOperator $operator, Datum $left, Datum $right): Datum
    {
        if (!in_array($operator, [BinaryOperator::Add, BinaryOperator::Subtract, BinaryOperator::Multiply, BinaryOperator::Divide], true)) {
            throw new InvalidArgumentException(sprintf('%s is not an arithmetic operator', $operator->spelling()));
        }
        if ($left->kind() === DatumKind::Null || $right->kind() === DatumKind::Null) {
            return new NullDatum();
        }
        $first = NumberArgument::exact($left);
        $second = NumberArgument::exact($right);
        if ($first !== null && $second !== null) {
            if ($operator === BinaryOperator::Add) {
                return ExactArithmetic::add($first, $second);
            }
            if ($operator === BinaryOperator::Subtract) {
                return ExactArithmetic::subtract($first, $second);
            }

            return $operator === BinaryOperator::Multiply
                ? ExactArithmetic::multiply($first, $second)
                : ExactArithmetic::divide($first, $second);
        }

        return self::approximate($operator, (float) NumberArgument::of($left), (float) NumberArgument::of($right));
    }

    /**
     * Applies an arithmetic operator to two approximate numbers.
     *
     * @param BinaryOperator $operator What to apply
     * @param float          $left     The number on its left
     * @param float          $right    The number on its right
     *
     * @example Approximate division keeps its fraction
     *     \App\Gql\Evaluation\Arithmetic::approximate(\App\Gql\Syntax\Expression\BinaryOperator::Divide, 7.0, 2.0)->toText() // => '3.5'
     * @example It still cannot divide by zero
     *     \App\Gql\Evaluation\Arithmetic::approximate(\App\Gql\Syntax\Expression\BinaryOperator::Divide, 1.0, 0.0) // throws \App\Gql\GqlException: division by zero
     *
     * @return FloatDatum The result
     *
     * @throws GqlException             If the divisor is zero
     * @throws InvalidArgumentException If the operator is not an arithmetic one
     */
    public static function approximate(BinaryOperator $operator, float $left, float $right): FloatDatum
    {
        if (!in_array($operator, [BinaryOperator::Add, BinaryOperator::Subtract, BinaryOperator::Multiply, BinaryOperator::Divide], true)) {
            throw new InvalidArgumentException(sprintf('%s is not an arithmetic operator', $operator->spelling()));
        }
        if ($operator === BinaryOperator::Divide && $right === 0.0) {
            throw GqlException::because(StatusCode::DivisionByZero, 'a number cannot be divided by zero');
        }

        $result = $operator === BinaryOperator::Divide ? $left / $right : $left * $right;
        if ($operator === BinaryOperator::Add) {
            $result = $left + $right;
        }
        if ($operator === BinaryOperator::Subtract) {
            $result = $left - $right;
        }

        return new FloatDatum($result);
    }

    /**
     * Returns a number with its sign reversed.
     *
     * @param Datum $value The number
     *
     * @example An exact number stays exact
     *     \App\Gql\Evaluation\Arithmetic::negate(new \App\Gql\Datum\DecimalDatum(15, 1))->toText() // => '-1.5'
     * @example The absence of a number has no sign to reverse
     *     \App\Gql\Evaluation\Arithmetic::negate(new \App\Gql\Datum\NullDatum())->kind() // => \App\Gql\Datum\DatumKind::Null
     *
     * @return Datum The number, negated
     *
     * @throws GqlException If the value is not a number, or its negation is out of range
     */
    public static function negate(Datum $value): Datum
    {
        if ($value->kind() === DatumKind::Null) {
            return new NullDatum();
        }
        $exact = NumberArgument::exact($value);
        if ($exact === null) {
            return new FloatDatum(-(float) NumberArgument::of($value));
        }

        return ExactArithmetic::subtract(new IntegerDatum(0), $exact);
    }
}
