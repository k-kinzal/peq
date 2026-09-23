<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\Resolution;

use App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph;
use App\Analyzer\ExperimentAnalyzer\Flow\State;
use PhpParser\Node;

/**
 * Symbolic back edges retain changing definitions without claiming feasible counts.
 */
final readonly class Repetition
{
    /**
     * Shares one callable graph with the structured loop evaluator.
     */
    public function __construct(private DependencyGraph $graph) {}

    /**
     * Introduces unresolved loop-carried inputs only for variables a back edge changes.
     *
     * @param non-empty-list<State> $back
     *
     * @return array<string, string>
     */
    public function open(Node $node, State $state, array $back): array
    {
        $iteration = $this->graph->record($node, 'loop-iteration');
        $this->graph->issues[$iteration] = new Issue('LOOP_RECURRENCE', $iteration, 'structured-loops/v1', 'Loop structure is modeled; feasible iteration counts, termination and correlations between iterations are not solved.', $this->graph->nodes[$iteration], ['value', 'control']);
        $inputs = [];
        foreach ($state->definitions as $name => $definitions) {
            foreach ($back as $path) {
                if (($path->definitions[$name] ?? []) !== $definitions) {
                    $id = $this->graph->record($node, 'loop-input', $name);
                    foreach ($definitions as $definition => $_) {
                        $this->graph->connect($id, $definition, 'initial-definition');
                    }
                    $this->graph->connect($id, $iteration, 'control', 'first-or-later-iteration');
                    $state->definitions[$name] = [$id => true];
                    $inputs[$name] = $id;

                    break;
                }
            }
        }
        $state->controls[$iteration] = 'first-or-later-iteration';

        return $inputs;
    }

    /**
     * Connects the repeating paths, including their conditions, to the next header.
     *
     * @param array<string, string> $inputs
     * @param list<State>           $back
     */
    public function close(Node $node, array $inputs, array $back): void
    {
        $iteration = $this->graph->record($node, 'loop-iteration');
        foreach ($back as $path) {
            foreach ($inputs as $name => $id) {
                foreach ($path->definitions[$name] ?? [] as $definition => $_) {
                    if ($id !== $definition) {
                        $this->graph->connect($id, $definition, 'loop-carried');
                    }
                }
            }
            foreach ($path->controls as $condition => $branch) {
                if ($condition !== $iteration) {
                    $this->graph->connect($iteration, $condition, 'repeat', $branch);
                }
            }
        }
    }
}
