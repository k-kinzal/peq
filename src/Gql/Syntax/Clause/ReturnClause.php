<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Clause;

use App\Gql\Syntax\Clause;
use App\Gql\Syntax\Expression;

/**
 * What the reader is shown, and how the rows behind it are summarised.
 *
 * `RETURN` carries more than the other clauses because GQL puts more in it: the
 * columns, whether duplicates are dropped, what the rows are grouped by, and the
 * ordering and paging that apply to the result rather than to the rows going into it.
 * That last point is the reason they are here rather than as separate clauses after
 * it — an `ORDER BY` written after a `RETURN` orders what the reader sees, while one
 * written before it orders what the projection is computed from.
 *
 * Returning everything — `RETURN *` — is written as no columns at all, because that
 * is what it means: every name in scope, decided when the query runs rather than when
 * it is written.
 */
final readonly class ReturnClause implements Clause
{
    /**
     * @param list<Projection> $columns  What the reader is shown, or nothing at all to show every name in scope
     * @param bool             $distinct Whether rows that repeat are shown once
     * @param list<Expression> $groupBy  What the rows are grouped by, or nothing when they are not grouped
     * @param list<SortKey>    $orderBy  How the result is ordered, most significant first
     * @param null|PageClause  $page     Which stretch of the result is shown, or null for all of it
     */
    public function __construct(
        public array $columns = [],
        public bool $distinct = false,
        public array $groupBy = [],
        public array $orderBy = [],
        public ?PageClause $page = null,
    ) {}

    /**
     * Reports whether the query asks for every name in scope.
     *
     * @example A projection that names no column asks for all of them
     *     (new \App\Gql\Syntax\Clause\ReturnClause())->everything() // => true
     *
     * @return bool True when no column was named
     */
    public function everything(): bool
    {
        return $this->columns === [];
    }
}
