<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\Resolution;

use App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph;

/**
 * Closure concerns the modeled local origins, not knowledge of runtime values.
 */
final readonly class Assessment
{
    /**
     * @param array<string, string> $nodes
     * @param list<Issue>           $issues
     * @param list<string>          $frontier
     */
    public function __construct(
        public string $status = 'not-analyzed',
        public bool $complete = false,
        public array $nodes = [],
        public array $issues = [],
        public array $frontier = [],
    ) {}

    /**
     * Propagates unknown and input origins independently of traversal direction.
     *
     * @param list<string> $selected
     * @param list<string> $frontier
     */
    public static function of(DependencyGraph $graph, array $selected, array $frontier): self
    {
        $unknown = array_fill_keys(array_keys($graph->issues), true);
        $input = [];
        foreach ($graph->nodes as $id => $node) {
            if (in_array($node->kind, ['parameter', 'receiver'], true)) {
                $input[$id] = true;
            } elseif (in_array($node->kind, ['unbound', 'undefined', 'boundary', 'call', 'heap-write'], true)) {
                $unknown[$id] = true;
            }
        }
        $unknown = self::propagate($graph, $unknown);
        $input = self::propagate($graph, $input);
        $states = [];
        foreach ($selected as $id) {
            $states[$id] = isset($unknown[$id]) ? (isset($graph->issues[$id]) ? ($graph->issues[$id]->code === 'NOT_ANALYZED' ? 'not-analyzed' : 'unknown') : 'partial') : (isset($input[$id]) ? 'input' : 'resolved');
        }
        $incomplete = $graph->issues !== [] || array_intersect_key($unknown, $states) !== [];
        $status = $frontier !== [] ? 'truncated' : ($incomplete ? 'partial' : (in_array('input', $states, true) ? 'input' : 'resolved'));

        $issues = array_values($graph->issues);
        foreach ($frontier as $id) {
            $issues[] = new Issue('TRAVERSAL_LIMIT', $id, 'slice-depth/v1', 'The requested depth leaves unexplored dependencies.', $graph->nodes[$id], ['traversal']);
        }

        return new self($status, !$incomplete && $frontier === [], $states, $issues, $frontier);
    }

    /**
     * Computes the transitive dependence on a set of boundary nodes.
     *
     * @param array<string, true> $seeds
     *
     * @return array<string, true>
     */
    public static function propagate(DependencyGraph $graph, array $seeds): array
    {
        $dependents = [];
        foreach ($graph->edges as $edge) {
            $dependents[$edge->to][] = $edge->from;
        }
        $queue = array_keys($seeds);
        for ($i = 0; $i < count($queue); ++$i) {
            foreach ($dependents[$queue[$i]] ?? [] as $id) {
                if (!isset($seeds[$id])) {
                    $seeds[$id] = true;
                    $queue[] = $id;
                }
            }
        }

        return $seeds;
    }
}
