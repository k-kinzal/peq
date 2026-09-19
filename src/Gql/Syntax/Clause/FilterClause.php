<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Clause;

use App\Gql\Syntax\Clause;
use App\Gql\Syntax\Expression;

/**
 * Keeping only the rows a predicate holds of.
 *
 * Only rows where the predicate is true survive. A row where it is false is dropped,
 * and so is a row where it could not be decided — which is the practical face of
 * three-valued logic and the reason `FILTER NOT (p.line > 0)` does not keep the rows
 * that have no line.
 */
final class FilterClause implements Clause
{
    /**
     * @param Expression $predicate What must hold of a row for it to be kept
     */
    public function __construct(
        public readonly Expression $predicate,
    ) {}
}
