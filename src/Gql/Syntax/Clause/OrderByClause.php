<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Clause;

use App\Gql\Syntax\Clause;

/**
 * Putting the rows in an order.
 *
 * GQL is explicit that only the clause immediately after an `ORDER BY` can rely on
 * the order it established. That is why a top-k query writes `ORDER BY` and then
 * `LIMIT`: the limit takes the first rows of the order, and anything between them
 * would be free to lose it.
 */
final class OrderByClause implements Clause
{
    /**
     * @param list<SortKey> $keys What to order by, most significant first
     */
    public function __construct(
        public readonly array $keys,
    ) {
        assert($this->keys !== [], 'An ordering orders by at least one thing');
    }
}
