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
     * @param list<string>                                          $roots
     * @param list<Occurrence>                                      $nodes
     * @param list<Dependency>                                      $edges
     * @param list<string>                                          $diagnostics
     * @param list<\App\Analyzer\ExperimentAnalyzer\Structure\Site> $structure
     * @param array<string, null|bool|int|list<string>|string>      $provenance
     */
    public function __construct(
        public string $target,
        public string $file,
        public Direction $direction,
        public array $roots,
        public array $nodes,
        public array $edges,
        public array $diagnostics,
        public \App\Analyzer\ExperimentAnalyzer\Resolution\Assessment $analysis = new \App\Analyzer\ExperimentAnalyzer\Resolution\Assessment(),
        public array $structure = [],
        public array $provenance = [],
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
        $frontier = [];
        for ($index = 0; $index < count($queue); ++$index) {
            $id = $queue[$index];
            if ($level !== null && $depths[$id] >= $level) {
                if (($adjacency[$id] ?? []) !== []) {
                    $frontier[] = $id;
                }

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

        $assessment = \App\Analyzer\ExperimentAnalyzer\Resolution\Assessment::of($graph, $queue, $frontier);
        $warnings = array_map(static fn (\App\Analyzer\ExperimentAnalyzer\Resolution\Issue $issue): string => '['.$issue->code.'] Line '.$issue->source->line.': '.$issue->reason, $assessment->issues);

        return new self($graph->target, $graph->file, $direction, $roots, $nodes, $edges, [...array_values($graph->diagnostics), ...$warnings], $assessment, array_values($graph->inventory->sites ?? []), $graph->provenance);
    }
}
