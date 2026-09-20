<?php

declare(strict_types=1);

namespace App\Gql\Evaluation;

use App\Gql\Datum\Datum;
use App\Gql\GqlException;
use App\Gql\Syntax\Expression\BinaryOperator;

/**
 * Which rule an operator written between two values follows.
 *
 * Nineteen operators, five sets of rules. Sorting one into the other is a single
 * closed decision, which is what makes adding an operator a change in two places
 * rather than a search for every place that might have had an opinion.
 *
 * @visibility App\Gql
 */
final class BinaryOperation
{
    /**
     * Applies an operator to two values.
     *
     * @param BinaryOperator $operator What to apply
     * @param Datum          $left     The value on its left
     * @param Datum          $right    The value on its right
     *
     * @example An arithmetic operator follows the arithmetic rules
     *     \App\Gql\Evaluation\BinaryOperation::apply(\App\Gql\Syntax\Expression\BinaryOperator::Multiply, new \App\Gql\Datum\IntegerDatum(2), new \App\Gql\Datum\IntegerDatum(3))->toText() // => '6'
     * @example A logical one follows three-valued logic
     *     \App\Gql\Evaluation\BinaryOperation::apply(\App\Gql\Syntax\Expression\BinaryOperator::And, new \App\Gql\Datum\BooleanDatum(false), new \App\Gql\Datum\NullDatum())->toText() // => 'FALSE'
     * @example A membership test follows the rules about lists
     *     $within = new \App\Gql\Datum\ListDatum([new \App\Gql\Datum\IntegerDatum(1)]);
     *     \App\Gql\Evaluation\BinaryOperation::apply(\App\Gql\Syntax\Expression\BinaryOperator::NotIn, new \App\Gql\Datum\IntegerDatum(2), $within)->toText() // => 'TRUE'
     *
     * @return Datum What the operator produced
     *
     * @throws GqlException If the values are not what the operator accepts
     */
    public static function apply(BinaryOperator $operator, Datum $left, Datum $right): Datum
    {
        return match ($operator) {
            BinaryOperator::Add,
            BinaryOperator::Subtract,
            BinaryOperator::Multiply,
            BinaryOperator::Divide => Arithmetic::apply($operator, $left, $right),

            BinaryOperator::Concatenate => TextOperation::concatenate($left, $right),

            BinaryOperator::Equal,
            BinaryOperator::NotEqual,
            BinaryOperator::Less,
            BinaryOperator::LessOrEqual,
            BinaryOperator::Greater,
            BinaryOperator::GreaterOrEqual => Comparison::apply($operator, $left, $right),

            BinaryOperator::In => Membership::of($left, $right),
            BinaryOperator::NotIn => Logic::datum(Logic::negate(Logic::truth(Membership::of($left, $right)))),

            BinaryOperator::And => Logic::datum(Logic::both(Logic::truth($left), Logic::truth($right))),
            BinaryOperator::Or => Logic::datum(Logic::either(Logic::truth($left), Logic::truth($right))),
            BinaryOperator::Xor => Logic::datum(Logic::exclusive(Logic::truth($left), Logic::truth($right))),
        };
    }
}
