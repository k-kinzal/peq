<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Clause;

use App\Gql\Syntax\Expression;

/**
 * One thing rows are ordered by, and which way.
 *
 * Sort keys are tried in the order they are written, the later ones only deciding
 * between rows the earlier ones tied on. That is what makes a multi-key sort
 * predictable, and it is what a paged query needs in order to page consistently.
 */
final readonly class SortKey
{
    /**
     * @param Expression    $value     What to order by
     * @param SortDirection $direction Which way to order it
     */
    public function __construct(
        public Expression $value,
        public SortDirection $direction = SortDirection::Ascending,
    ) {}
}
