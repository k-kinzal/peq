<?php

declare(strict_types=1);

namespace Tests\Fixture\Reporter;

use App\Analyzer\Graph\Node;
use Closure;

/**
 * A record of which symbols a traversal visited, and how deep each one sat.
 *
 * A traversal reports its work by calling a visitor rather than by returning
 * anything, so reading it back means keeping what the visitor was told. Doing that
 * in a closure inside every test would put the bookkeeping in the way of the
 * assertion; doing it here leaves each test with one call and one expectation.
 */
final class VisitLog
{
    /**
     * @var list<Node> The nodes the traversal handed over, in order
     */
    private array $nodes = [];

    /**
     * @var list<int> The depth reported for each of those nodes
     */
    private array $depths = [];

    /**
     * Returns a visitor that records every symbol and descends into all of them.
     *
     * @return Closure(Node, int): bool The visitor to hand to a traversal
     */
    public function recorder(): Closure
    {
        return function (Node $node, int $depth): bool {
            $this->nodes[] = $node;
            $this->depths[] = $depth;

            return true;
        };
    }

    /**
     * Returns a visitor that records every symbol but stops below a given depth.
     *
     * @param int $deepest The last depth the traversal is allowed to descend from
     *
     * @return Closure(Node, int): bool The visitor to hand to a traversal
     */
    public function recorderStoppingBelow(int $deepest): Closure
    {
        return function (Node $node, int $depth) use ($deepest): bool {
            $this->nodes[] = $node;
            $this->depths[] = $depth;

            return $depth < $deepest;
        };
    }

    /**
     * Returns the nodes the traversal handed over, in the order it did.
     *
     * @return list<Node> The visited nodes
     */
    public function nodes(): array
    {
        return $this->nodes;
    }

    /**
     * Returns the name of every symbol the traversal handed over, in order.
     *
     * @return list<string> The visited names
     */
    public function names(): array
    {
        return array_map(static fn (Node $node): string => $node->id()->toString(), $this->nodes);
    }

    /**
     * Returns the depth the traversal first reported for a symbol.
     *
     * @param string $name The name of the symbol
     *
     * @return null|int The depth, or null when the symbol was never visited
     */
    public function depthOf(string $name): ?int
    {
        foreach ($this->names() as $position => $visited) {
            if ($visited === $name) {
                return $this->depths[$position];
            }
        }

        return null;
    }
}
