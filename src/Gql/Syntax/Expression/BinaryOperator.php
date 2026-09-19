<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Expression;

/**
 * An operator written between the two values it acts on.
 *
 * The set is GQL's, and so is the order they bind in. That order is the one thing
 * about operators a reader cannot check by looking: `NOT a OR b` means `(NOT a) OR b`
 * and `a OR b AND c` means `a OR (b AND c)`, and a query written on the other
 * assumption is wrong in a way that still runs. Stating the order here, once, is what
 * lets the parser be right about it everywhere.
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

    /** Whether a value is among the values of a list */
    case In;

    /** Whether a value is not among the values of a list */
    case NotIn;

    /** Whether a string holds another string anywhere */
    case Contains;

    /** Whether a string begins with another string */
    case StartsWith;

    /** Whether a string ends with another string */
    case EndsWith;

    /** Whether both sides are true */
    case And;

    /** Whether exactly one side is true */
    case Xor;

    /** Whether either side is true */
    case Or;

    /**
     * Returns how tightly the operator binds, the lower the tighter.
     *
     * The numbers are GQL's own ranking, written out: multiplication before addition,
     * addition before comparison, comparison before negation, and the three logical
     * connectives last, in the order `AND`, `XOR`, `OR`.
     *
     * @example Multiplication binds tighter than addition
     *     \App\Gql\Syntax\Expression\BinaryOperator::Multiply->binding() < \App\Gql\Syntax\Expression\BinaryOperator::Add->binding() // => true
     * @example Conjunction binds tighter than disjunction
     *     \App\Gql\Syntax\Expression\BinaryOperator::And->binding() < \App\Gql\Syntax\Expression\BinaryOperator::Or->binding() // => true
     *
     * @return int Where the operator stands in the order of binding
     */
    public function binding(): int
    {
        return match ($this) {
            self::Multiply, self::Divide => 2,
            self::Add, self::Subtract, self::Concatenate => 3,
            self::Equal, self::NotEqual, self::Less, self::LessOrEqual,
            self::Greater, self::GreaterOrEqual, self::In, self::NotIn,
            self::Contains, self::StartsWith, self::EndsWith => 4,
            self::And => 6,
            self::Xor => 7,
            self::Or => 8,
        };
    }

    /**
     * Writes the operator the way a query writes it.
     *
     * @example An operator written as a symbol reads as that symbol
     *     \App\Gql\Syntax\Expression\BinaryOperator::NotEqual->spelling() // => '<>'
     * @example One written as words reads as those words
     *     \App\Gql\Syntax\Expression\BinaryOperator::StartsWith->spelling() // => 'STARTS WITH'
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
            self::In => 'IN',
            self::NotIn => 'NOT IN',
            self::Contains => 'CONTAINS',
            self::StartsWith => 'STARTS WITH',
            self::EndsWith => 'ENDS WITH',
            self::And => 'AND',
            self::Xor => 'XOR',
            self::Or => 'OR',
        };
    }
}
