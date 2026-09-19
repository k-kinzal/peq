<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Expression;

use App\Gql\Syntax\Expression;

/**
 * A list written out in the query.
 *
 * Its values are expressions rather than literals, because GQL allows them to be:
 * `[p.firstName, p.lastName]` is a list, and so is the right-hand side of an `IN`
 * that was written with something computed in it.
 */
final class ListExpression implements Expression
{
    /**
     * @param list<Expression> $items The values, in the order they were written
     */
    public function __construct(
        public readonly array $items = [],
    ) {}
}
