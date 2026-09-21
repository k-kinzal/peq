<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Pattern;

/**
 * Everything one MATCH looks for, as several paths at once.
 *
 * Paths written side by side, separated by commas, are matched together, and the
 * variables they share are what ties them into one shape. `(p)-[:declaresMethod]->(m),
 * (p)-[:extends]->(q)` is not two searches but one: a class with both a method and a
 * parent, found in a single pass.
 *
 * Writing a complicated shape as several simple paths rather than one long one is the
 * usual way to keep it readable, which is why this is a list rather than an
 * afterthought.
 */
final readonly class GraphPattern
{
    /**
     * @param list<PathPattern> $paths The paths matched together
     */
    public function __construct(
        public array $paths,
    ) {
        assert($this->paths !== [], 'A pattern looks for at least one path');
    }
}
