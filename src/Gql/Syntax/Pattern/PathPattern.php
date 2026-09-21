<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Pattern;

/**
 * One path through the graph, as a query describes it.
 *
 * A path pattern alternates between things that match nodes and things that match
 * edges, and always begins and ends at a node. That is what makes it a path rather
 * than a collection of requirements, and it is what a path variable binds to when the
 * query asks for one.
 *
 * The mode travels with the path rather than with the statement, because it is a
 * property of this traversal: one pattern in a `MATCH` may need to forbid repeating a
 * node while another beside it does not care.
 */
final readonly class PathPattern
{
    /**
     * @param list<PathTerm> $terms    The pieces of the path, beginning and ending with one that matches a node
     * @param PathMode       $mode     What the path may visit more than once, a walk restricting nothing when none is written
     * @param null|string    $variable What to bind the matched path to, or null when the path is not named
     */
    public function __construct(
        public array $terms,
        public PathMode $mode = PathMode::Walk,
        public ?string $variable = null,
    ) {
        assert($this->terms !== [], 'A path passes through at least one node');
    }
}
