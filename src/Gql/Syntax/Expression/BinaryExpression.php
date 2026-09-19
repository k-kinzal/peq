<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Expression;

use App\Gql\Syntax\Expression;

/**
 * An operator applied to two values.
 *
 * Arithmetic, comparison, logic, list membership and the string predicates are all
 * one shape, because they are all one thing done to two values. What differs between
 * them — which types they accept, what they do with an absent value — is a question
 * about the operator, and is answered where the operator is applied rather than by
 * having nineteen shapes.
 */
final class BinaryExpression implements Expression
{
    /**
     * @param BinaryOperator $operator What is applied
     * @param Expression     $left     The value on its left
     * @param Expression     $right    The value on its right
     */
    public function __construct(
        public readonly BinaryOperator $operator,
        public readonly Expression $left,
        public readonly Expression $right,
    ) {}
}
