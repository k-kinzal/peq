<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Expression;

use App\Gql\Syntax\Expression;

/**
 * An operator applied to one value.
 *
 * The null tests read as postfix in a query — `p.name IS NULL` — and as prefix here.
 * That is a difference in writing rather than in meaning: both are one operator over
 * one value, and holding them the same way is what keeps evaluation from needing a
 * separate shape for each side of the operand.
 */
final readonly class UnaryExpression implements Expression
{
    /**
     * @param UnaryOperator $operator What is applied
     * @param Expression    $operand  What it is applied to
     */
    public function __construct(
        public UnaryOperator $operator,
        public Expression $operand,
    ) {}
}
