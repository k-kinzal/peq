<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Clause;

use App\Gql\Syntax\Clause;
use App\Gql\Syntax\Expression;
use App\Gql\Syntax\Pattern\GraphPattern;

/**
 * Finding a shape in the graph, and joining it to what is already known.
 *
 * A `MATCH` after another clause is a join, not a fresh search: the variables the
 * pattern shares with the rows it is given are what the two are joined on. That is
 * how a query builds up an answer in steps — find the controllers, then find what
 * each of them reaches — without ever writing a join condition.
 *
 * An optional match keeps a row that matched nothing, with the pattern's own
 * variables absent, which is the only way to ask "and what, if anything, does each of
 * these do" without silently dropping the ones that do nothing.
 */
final readonly class MatchClause implements Clause
{
    /**
     * @param GraphPattern    $pattern  The shape to look for
     * @param null|Expression $where    What must hold of a match for it to be kept, or null when every match is kept
     * @param bool            $optional Whether a row that matches nothing is kept rather than dropped
     */
    public function __construct(
        public GraphPattern $pattern,
        public ?Expression $where = null,
        public bool $optional = false,
    ) {}
}
