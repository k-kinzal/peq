<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\Resolution;

use App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph;
use App\Analyzer\ExperimentAnalyzer\Flow\State;
use PhpParser\Node;

/**
 * Joins continuing paths as a disjunction of conjunctions, preserving early exits.
 */
final class Guards
{
    /**
     * @param non-empty-list<State> $states
     */
    public function join(Node $node, array $states, DependencyGraph $graph): State
    {
        $joined = State::join($states);
        $paths = $this->simplify(array_map(static fn (State $state): array => $state->controls, $states));
        if (count($paths) === 1) {
            $joined->controls = $paths[0];

            return $joined;
        }
        $id = $graph->record($node, 'any-path');
        foreach ($paths as $index => $conditions) {
            $path = $graph->record($node, 'all-conditions-'.$index);
            foreach ($conditions as $condition => $branch) {
                $graph->connect($path, $condition, 'control', $branch);
            }
            $graph->connect($id, $path, 'alternative');
        }
        $joined->controls = [$id => 'any-path'];

        return $joined;
    }

    /**
     * Replaces an expression state with exactly its continuing alternatives.
     *
     * @param list<State> $states
     */
    public function continueWith(Node $node, State $state, array $states, DependencyGraph $graph): void
    {
        $live = array_values(array_filter($states, static fn (State $path): bool => $path->reachable));
        $state->reachable = $live !== [];
        if ($live !== []) {
            $joined = $this->join($node, $live, $graph);
            $state->definitions = $joined->definitions;
            $state->controls = $joined->controls;
        }
    }

    /**
     * Eliminates subsumed paths and merges exactly complementary final predicates.
     *
     * @param non-empty-list<array<string, string>> $paths
     *
     * @return non-empty-list<array<string, string>>
     */
    public function simplify(array $paths): array
    {
        for ($i = 0; $i < count($paths); ++$i) {
            for ($j = $i + 1; $j < count($paths); ++$j) {
                $common = array_intersect_assoc($paths[$i], $paths[$j]);
                $left = array_diff_assoc($paths[$i], $common);
                $right = array_diff_assoc($paths[$j], $common);
                $complement = count($left) === 1 && array_keys($left) === array_keys($right)
                    && in_array(array_values($left), [['truthy'], ['falsy'], ['null-or-unset'], ['non-null']], true)
                    && in_array([array_values($left), array_values($right)], [[['truthy'], ['falsy']], [['falsy'], ['truthy']], [['null-or-unset'], ['non-null']], [['non-null'], ['null-or-unset']]], true);
                if ($left === [] || $right === [] || $complement) {
                    $paths[$i] = $common;
                    unset($paths[$j]);

                    return $this->simplify(array_values($paths));
                }
            }
        }

        return $paths;
    }
}
