<?php

declare(strict_types=1);

namespace App\Gql\Evaluation;

use App\Gql\Argument\NumberArgument;
use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\FloatDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\GqlException;
use App\Gql\StatusCode;
use App\Gql\Syntax\Expression\BinaryOperator;

/**
 * Arithmetic, under GQL's rules about what mixing kinds of number produces.
 *
 * Two rules decide everything here, and both are the standard's. An expression that
 * meets an approximate number produces an approximate one, so `1 + 1.5` is 2.5 and
 * not 2. And an expression that meets the absence of a value produces the absence of
 * one, so a sum over a property some symbols do not carry is absent rather than
 * wrong.
 *
 * Division of two whole numbers stays whole, as it does in SQL. That is what makes
 * `p.birthday / 10000` a birth year rather than a fraction, and it is why the one
 * failure arithmetic can have — dividing by zero — is reported rather than quietly
 * turned into infinity the way PHP's own division would.
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
     * @example Whole numbers stay whole
     *     \App\Gql\Evaluation\Arithmetic::apply(\App\Gql\Syntax\Expression\BinaryOperator::Add, new \App\Gql\Datum\IntegerDatum(1), new \App\Gql\Datum\IntegerDatum(2))->toText() // => '3'
     * @example Meeting an approximate number makes the result approximate
     *     \App\Gql\Evaluation\Arithmetic::apply(\App\Gql\Syntax\Expression\BinaryOperator::Add, new \App\Gql\Datum\IntegerDatum(1), new \App\Gql\Datum\FloatDatum(1.5))->toText() // => '2.5'
     * @example Meeting the absence of a value produces the absence of one
     *     \App\Gql\Evaluation\Arithmetic::apply(\App\Gql\Syntax\Expression\BinaryOperator::Add, new \App\Gql\Datum\IntegerDatum(1), new \App\Gql\Datum\NullDatum())->kind() // => \App\Gql\Datum\DatumKind::Null
     *
     * @return Datum What the operator produced
     *
     * @throws GqlException If a value is not a number, or a division is by zero
     */
    public static function apply(BinaryOperator $operator, Datum $left, Datum $right): Datum
    {
        if ($left->kind() === DatumKind::Null || $right->kind() === DatumKind::Null) {
            return new NullDatum();
        }

        $approximate = NumberArgument::approximate($left) || NumberArgument::approximate($right);
        $first = NumberArgument::of($left);
        $second = NumberArgument::of($right);

        if ($operator === BinaryOperator::Divide) {
            return self::divide($first, $second, $approximate);
        }

        $result = $first * $second;
        if ($operator === BinaryOperator::Add) {
            $result = $first + $second;
        }
        if ($operator === BinaryOperator::Subtract) {
            $result = $first - $second;
        }

        return $approximate ? new FloatDatum((float) $result) : new IntegerDatum((int) $result);
    }

    /**
     * Divides one number by another, keeping whole numbers whole.
     *
     * @param float|int $left        The number being divided
     * @param float|int $right       The number to divide it by
     * @param bool      $approximate Whether either of them is approximate
     *
     * @example Two whole numbers divide into a whole number
     *     \App\Gql\Evaluation\Arithmetic::divide(19990101, 10000, false)->toText() // => '1999'
     * @example An approximate number divides into an approximate one
     *     \App\Gql\Evaluation\Arithmetic::divide(3, 2.0, true)->toText() // => '1.5'
     * @example Dividing by zero is reported rather than guessed at
     *     \App\Gql\Evaluation\Arithmetic::divide(1, 0, false) // throws \App\Gql\GqlException: division by zero
     * @example Including when the zero is an approximate one
     *     \App\Gql\Evaluation\Arithmetic::divide(1.0, 0.0, true) // throws \App\Gql\GqlException: division by zero
     *
     * @return Datum The quotient
     *
     * @throws GqlException If the divisor is zero
     */
    public static function divide(float|int $left, float|int $right, bool $approximate): Datum
    {
        if ($right === 0 || $right === 0.0) {
            throw GqlException::because(StatusCode::DivisionByZero, 'a number cannot be divided by zero');
        }
        if ($approximate) {
            return new FloatDatum($left / $right);
        }

        return new IntegerDatum(intdiv((int) $left, (int) $right));
    }

    /**
     * Returns a number with its sign reversed.
     *
     * @param Datum $value The number
     *
     * @example A whole number keeps being whole
     *     \App\Gql\Evaluation\Arithmetic::negate(new \App\Gql\Datum\IntegerDatum(3))->toText() // => '-3'
     * @example An approximate one keeps being approximate
     *     \App\Gql\Evaluation\Arithmetic::negate(new \App\Gql\Datum\FloatDatum(1.5))->toText() // => '-1.5'
     * @example The absence of a value has no sign to reverse
     *     \App\Gql\Evaluation\Arithmetic::negate(new \App\Gql\Datum\NullDatum())->kind() // => \App\Gql\Datum\DatumKind::Null
     *
     * @return Datum The number with its sign reversed
     *
     * @throws GqlException If the value is not a number
     */
    public static function negate(Datum $value): Datum
    {
        if ($value->kind() === DatumKind::Null) {
            return new NullDatum();
        }

        $number = NumberArgument::of($value);

        return NumberArgument::approximate($value)
            ? new FloatDatum(-(float) $number)
            : new IntegerDatum(-(int) $number);
    }
}
