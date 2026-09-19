<?php

declare(strict_types=1);

namespace App\Gql\Evaluation;

use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumKind;
use App\Gql\GqlException;
use App\Gql\Syntax\Expression\UnaryOperator;

/**
 * Which rule an operator written before one value follows.
 *
 * The two null tests are the only expressions in GQL that can never come out
 * undecided, and that is what they are for: they are the way out of three-valued
 * logic, the one question about an absent value that has a definite answer.
 *
 * @visibility App\Gql
 */
final class UnaryOperation
{
    /**
     * Applies an operator to one value.
     *
     * @param UnaryOperator $operator What to apply
     * @param Datum         $operand  The value it is applied to
     *
     * @example A negation follows three-valued logic
     *     \App\Gql\Evaluation\UnaryOperation::apply(\App\Gql\Syntax\Expression\UnaryOperator::Not, new \App\Gql\Datum\NullDatum())->kind() // => \App\Gql\Datum\DatumKind::Null
     * @example A null test always has an answer
     *     \App\Gql\Evaluation\UnaryOperation::apply(\App\Gql\Syntax\Expression\UnaryOperator::IsNull, new \App\Gql\Datum\NullDatum())->toText() // => 'TRUE'
     * @example A sign is arithmetic
     *     \App\Gql\Evaluation\UnaryOperation::apply(\App\Gql\Syntax\Expression\UnaryOperator::Negate, new \App\Gql\Datum\IntegerDatum(3))->toText() // => '-3'
     *
     * @return Datum What the operator produced
     *
     * @throws GqlException If the value is not what the operator accepts
     */
    public static function apply(UnaryOperator $operator, Datum $operand): Datum
    {
        return match ($operator) {
            UnaryOperator::Not => Logic::datum(Logic::negate(Logic::truth($operand))),
            UnaryOperator::Negate => Arithmetic::negate($operand),
            UnaryOperator::Identity => self::identity($operand),
            UnaryOperator::IsNull => Logic::datum($operand->kind() === DatumKind::Null),
            UnaryOperator::IsNotNull => Logic::datum($operand->kind() !== DatumKind::Null),
        };
    }

    /**
     * Returns a number as it stands, having checked that it is one.
     *
     * A plus sign written before a value changes nothing about it, but it still says
     * the value is meant to be a number, and a query that writes it over something
     * else has made a mistake worth reporting.
     *
     * @param Datum $operand The value
     *
     * @example A number written with a sign is the number
     *     \App\Gql\Evaluation\UnaryOperation::identity(new \App\Gql\Datum\IntegerDatum(3))->toText() // => '3'
     * @example Something that is not a number is reported
     *     \App\Gql\Evaluation\UnaryOperation::identity(new \App\Gql\Datum\StringDatum('3')) // throws \App\Gql\GqlException: invalid value type
     *
     * @return Datum The value
     *
     * @throws GqlException If the value is not a number
     */
    public static function identity(Datum $operand): Datum
    {
        if ($operand->kind() === DatumKind::Null) {
            return $operand;
        }
        Arithmetic::requireNumber($operand);

        return $operand;
    }
}
