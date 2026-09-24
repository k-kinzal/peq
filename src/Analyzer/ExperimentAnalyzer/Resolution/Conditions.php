<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\Resolution;

use App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph;

/**
 * Repeated origin tests require correlation reasoning beyond independent branches.
 */
final class Conditions
{
    /**
     * Marks a repeated variable-origin test instead of certifying infeasible paths.
     */
    public function observe(string $condition, DependencyGraph $graph): void
    {
        $dependencies = [];
        foreach ($graph->edges as $edge) {
            $definitionGuard = $edge->kind === 'control' && $graph->nodes[$edge->from]->kind === 'write';
            if (in_array($edge->kind, ['data', 'reaching-definition'], true) || $definitionGuard) {
                $dependencies[$edge->from][] = $edge->to;
            }
        }
        $queue = [$condition];
        $seen = [];
        $origins = [];
        for ($i = 0; $i < count($queue); ++$i) {
            $id = $queue[$i];
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            if ($graph->nodes[$id]->kind === 'parameter' || $graph->nodes[$id]->kind === 'write') {
                $origins[$id] = true;
            }
            array_push($queue, ...($dependencies[$id] ?? []));
        }
        if (array_intersect_key($origins, $graph->testedOrigins) !== []) {
            $graph->issues[$condition] ??= new Issue('PATH_CORRELATION', $condition, 'independent-branches/v1', 'Conditions share variable origins. Feasibility of combined alternatives is not solved.', $graph->nodes[$condition], ['value', 'control']);
        }
        $graph->testedOrigins += $origins;
    }
}
