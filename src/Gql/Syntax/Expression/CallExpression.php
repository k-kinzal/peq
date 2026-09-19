<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Expression;

use App\Gql\Syntax\Expression;

/**
 * A function applied to arguments.
 *
 * Aggregates are calls like any other here. Whether `count(p)` summarises a column or
 * computes over one row is not decided by how it is written — the same call means
 * different things over a plain table and over a group — so the shape stays one shape
 * and the decision is made where a result is projected.
 *
 * Two things a call can carry belong only to aggregates and are recorded because GQL
 * writes them inside the parentheses: `DISTINCT`, which drops repeated values before
 * summarising, and the star of `count(*)`, which counts rows rather than values and
 * so counts the ones whose value is absent.
 */
final class CallExpression implements Expression
{
    /**
     * @param string           $name      The function name, as the query wrote it
     * @param list<Expression> $arguments What it is applied to, in order
     * @param bool             $distinct  Whether repeated values are dropped before summarising
     * @param bool             $star      Whether it was written over rows rather than over a value
     */
    public function __construct(
        public readonly string $name,
        public readonly array $arguments = [],
        public readonly bool $distinct = false,
        public readonly bool $star = false,
    ) {
        assert($this->name !== '', 'A function is named');
    }
}
