<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\DataFlow;

use App\Analyzer\Graph\Direction;

/**
 * All reporters receive the same bounded walk, with edges in traversal direction.
 */
final readonly class Slice
{
    /**
     * @param list<string>     $roots
     * @param list<Occurrence> $nodes
     * @param list<Dependency> $edges
     * @param list<string>     $diagnostics
     */
    public function __construct(
        public string $target,
        public string $file,
        public Direction $direction,
        public array $roots,
        public array $nodes,
        public array $edges,
        public array $diagnostics,
    ) {}

    /**
     * @param list<string> $roots
     */
    public static function of(DependencyGraph $graph, array $roots, Direction $direction, ?int $level): self
    {
        $adjacency = [];
        foreach ($graph->edges as $edge) {
            $oriented = $direction === Direction::Uses ? $edge : new Dependency($edge->to, $edge->from, $edge->kind, $edge->branch);
            $adjacency[$oriented->from][] = $oriented;
        }
        $depths = array_fill_keys($roots, 0);
        $queue = $roots;
        $edges = [];
        for ($index = 0; $index < count($queue); ++$index) {
            $id = $queue[$index];
            if ($level !== null && $depths[$id] >= $level) {
                continue;
            }
            foreach ($adjacency[$id] ?? [] as $edge) {
                $edges[] = $edge;
                if (!isset($depths[$edge->to])) {
                    $depths[$edge->to] = $depths[$id] + 1;
                    $queue[] = $edge->to;
                }
            }
        }
        $nodes = array_map(static fn (string $id): Occurrence => $graph->nodes[$id], $queue);

        return new self($graph->target, $graph->file, $direction, $roots, $nodes, $edges, array_values($graph->diagnostics));
    }
}
