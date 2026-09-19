<?php

declare(strict_types=1);

namespace App\Gql\Syntax;

/**
 * A straight run of clauses, from one row in to one table out.
 *
 * This is what GQL calls a linear query: clauses one after another, each taking the
 * rows the last one produced. A whole query is one of these unless it combines
 * several with a set operator, which is why the two are separate shapes — the block
 * is where variables live and flow, and nothing flows across a set operator.
 */
final class QueryBlock
{
    /**
     * @param list<Clause> $clauses The clauses, in the order they run
     */
    public function __construct(
        public readonly array $clauses,
    ) {
        assert($this->clauses !== [], 'A query does at least one thing');
    }
}
