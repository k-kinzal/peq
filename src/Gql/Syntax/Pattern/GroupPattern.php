<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Pattern;

/**
 * A stretch of a path pattern written in parentheses, optionally repeated.
 *
 * A quantifier on a single edge repeats one step. A quantifier on a group repeats a
 * shape: `((:Method)-[:methodCall]->(:Method)){1,5}` follows a chain of calls, and
 * `((:Class)-[:declaresMethod]->()-[:methodCall]->()<-[:declaresMethod]-(:Class)){1,3}`
 * follows a chain of classes through the methods that connect them. Neither is
 * expressible by repeating an edge.
 */
final class GroupPattern implements PathTerm
{
    /**
     * @param list<PathTerm>  $terms      The stretch of pattern in the parentheses
     * @param null|Quantifier $quantifier How often it repeats, or null when it is matched exactly once
     */
    public function __construct(
        public readonly array $terms,
        public readonly ?Quantifier $quantifier = null,
    ) {
        assert($this->terms !== [], 'A group holds at least one piece of pattern');
    }
}
