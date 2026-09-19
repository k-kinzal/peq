<?php

declare(strict_types=1);

namespace App\Gql\Evaluation;

use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumOrder;
use App\Gql\Syntax\Expression\BinaryOperator;

/**
 * Comparing two values, under GQL's three-valued logic.
 *
 * The rules themselves belong to the values — what makes two numbers equal, how two
 * strings order — and are decided once there. What is decided here is only which
 * question an operator asks of them, and what an undecided answer looks like coming
 * back: the absence of a value, which a filter then drops.
 *
 * @visibility App\Gql
 */
final class Comparison
{
    /**
     * Applies a comparing operator to two values.
     *
     * @param BinaryOperator $operator What to apply
     * @param Datum          $left     The value on its left
     * @param Datum          $right    The value on its right
     *
     * @example Two numbers compare as numbers
     *     \App\Gql\Evaluation\Comparison::apply(\App\Gql\Syntax\Expression\BinaryOperator::Less, new \App\Gql\Datum\IntegerDatum(1), new \App\Gql\Datum\IntegerDatum(2))->toText() // => 'TRUE'
     * @example Nothing compares equal to the absence of a value
     *     \App\Gql\Evaluation\Comparison::apply(\App\Gql\Syntax\Expression\BinaryOperator::Equal, new \App\Gql\Datum\NullDatum(), new \App\Gql\Datum\NullDatum())->kind() // => \App\Gql\Datum\DatumKind::Null
     * @example Values of unrelated kinds are unequal rather than undecided
     *     \App\Gql\Evaluation\Comparison::apply(\App\Gql\Syntax\Expression\BinaryOperator::Equal, new \App\Gql\Datum\IntegerDatum(5), new \App\Gql\Datum\StringDatum('5'))->toText() // => 'FALSE'
     *
     * @return Datum True, false, or the absence of an answer
     */
    public static function apply(BinaryOperator $operator, Datum $left, Datum $right): Datum
    {
        if ($operator === BinaryOperator::Equal) {
            return Logic::datum(DatumOrder::equals($left, $right));
        }
        if ($operator === BinaryOperator::NotEqual) {
            return Logic::datum(Logic::negate(DatumOrder::equals($left, $right)));
        }

        $order = DatumOrder::compare($left, $right);
        if ($order === null) {
            return Logic::datum(null);
        }

        if ($operator === BinaryOperator::Less) {
            return Logic::datum($order < 0);
        }
        if ($operator === BinaryOperator::LessOrEqual) {
            return Logic::datum($order <= 0);
        }

        return Logic::datum($operator === BinaryOperator::Greater ? $order > 0 : $order >= 0);
    }
}
