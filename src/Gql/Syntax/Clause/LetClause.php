<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Clause;

use App\Gql\Syntax\Clause;

/**
 * Adding computed columns to every row.
 *
 * `LET` is what makes a long query readable: a value worked out once and named can be
 * filtered on, sorted by and returned without being written three times. It is also
 * how a query groups by something computed, since grouping is by name.
 */
final readonly class LetClause implements Clause
{
    /**
     * @param list<VariableBinding> $bindings The names being given, and what to
     */
    public function __construct(
        public array $bindings,
    ) {
        assert($this->bindings !== [], 'A binding clause binds at least one name');
    }
}
