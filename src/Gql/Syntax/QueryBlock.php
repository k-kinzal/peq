<?php

declare(strict_types=1);

namespace App\Gql\Syntax;

use App\Gql\Syntax\Clause\ReturnClause;

/**
 * A straight run of clauses, from one row in to one table out.
 *
 * This is what GQL calls a linear query: clauses one after another, each taking the
 * rows the last one produced, and a result statement at the end saying what to show.
 * A whole query is one of these unless it combines several with a set operator, which
 * is why the two are separate shapes — the block is where variables live and flow, and
 * nothing flows across a set operator.
 */
final readonly class QueryBlock
{
    /**
     * @param list<Clause> $clauses The clauses, in the order they run
     */
    public function __construct(
        public array $clauses,
    ) {
        assert($this->clauses !== [], 'A query does at least one thing');
        assert(
            $this->clauses[count($this->clauses) - 1] instanceof ReturnClause,
            'A linear query ends in the statement that says what to show',
        );
    }
}
