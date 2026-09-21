<?php

declare(strict_types=1);

namespace App\Gql\Syntax;

/**
 * A whole query: one or more linear blocks, combined left to right.
 *
 * The operators sit between the blocks, so there is always one fewer of them than
 * there are blocks. Holding them that way rather than as a tree keeps the combination
 * associating the way it reads — `A UNION B EXCEPT C` is `(A UNION B) EXCEPT C` — and
 * keeps a query with no set operator at all from needing a special shape.
 */
final readonly class Query
{
    /**
     * @param list<QueryBlock>  $blocks    The blocks, in the order they are written
     * @param list<SetOperator> $operators How each block is combined with what came before it
     */
    public function __construct(
        public array $blocks,
        public array $operators = [],
    ) {
        assert($this->blocks !== [], 'A query has at least one block');
        assert(count($this->operators) === count($this->blocks) - 1, 'One operator combines each block with what came before it');
    }

    /**
     * Returns the one block of a query that combines nothing.
     *
     * @param QueryBlock $block The block
     *
     * @example A query that combines nothing is its one block
     *     $block = new \App\Gql\Syntax\QueryBlock([new \App\Gql\Syntax\Clause\ReturnClause()]);
     *     count(\App\Gql\Syntax\Query::of($block)->operators) // => 0
     *
     * @return self The query
     */
    public static function of(QueryBlock $block): self
    {
        return new self([$block]);
    }
}
