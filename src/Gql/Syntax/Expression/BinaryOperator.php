<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Expression;

/**
 * An operator written between the two values it acts on.
 *
 * The set is GQL's. The order they bind in is not stated here but in the parser, one
 * method per production of ISO/IEC 39075, because that is where a reader can check it
 * against the grammar: `a OR b AND c` is `a OR (b AND c)`, and `a OR b XOR c` is
 * `(a OR b) XOR c`.
 */
enum BinaryOperator
{
    /** The sum of two numbers */
    case Add;

    /** The difference of two numbers */
    case Subtract;

    /** The product of two numbers */
    case Multiply;

    /** The quotient of two numbers */
    case Divide;

    /** Two strings written one after the other */
    case Concatenate;

    /** Whether two values are the same */
    case Equal;

    /** Whether two values differ */
    case NotEqual;

    /** Whether the left value orders before the right one */
    case Less;

    /** Whether the left value does not order after the right one */
    case LessOrEqual;

    /** Whether the left value orders after the right one */
    case Greater;

    /** Whether the left value does not order before the right one */
    case GreaterOrEqual;

    /** Whether both sides are true */
    case And;

    /** Whether exactly one side is true */
    case Xor;

    /** Whether either side is true */
    case Or;

    /**
     * Writes the operator the way a query writes it.
     *
     * @example An operator written as a symbol reads as that symbol
     *     \App\Gql\Syntax\Expression\BinaryOperator::NotEqual->spelling() // => '<>'
     * @example One written as a word reads as that word
     *     \App\Gql\Syntax\Expression\BinaryOperator::Xor->spelling() // => 'XOR'
     *
     * @return string The operator, as a query writes it
     */
    public function spelling(): string
    {
        return match ($this) {
            self::Add => '+',
            self::Subtract => '-',
            self::Multiply => '*',
            self::Divide => '/',
            self::Concatenate => '||',
            self::Equal => '=',
            self::NotEqual => '<>',
            self::Less => '<',
            self::LessOrEqual => '<=',
            self::Greater => '>',
            self::GreaterOrEqual => '>=',
            self::And => 'AND',
            self::Xor => 'XOR',
            self::Or => 'OR',
        };
    }
}
