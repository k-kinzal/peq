<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\Flow;

use App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;

/**
 * Connects source occurrences without losing assignment identity or guards.
 */
final readonly class Recording
{
    /**
     * Creates this value with its explicit analysis inputs.
     */
    public function __construct(public DependencyGraph $graph) {}

    /**
     * @param list<string> $inputs
     */
    public function value(Node $node, string $kind, array $inputs, State $state, ?string $variable = null): string
    {
        $id = $this->graph->record($node, $kind, $variable);
        $this->graph->scalars[$id] = \App\Analyzer\ExperimentAnalyzer\Resolution\ScalarOrigins::literal($node) || \App\Analyzer\ExperimentAnalyzer\Resolution\ScalarOrigins::inputs($inputs, $this->graph);
        foreach ($inputs as $input) {
            $this->graph->connect($id, $input, $kind === 'call' ? 'call-input' : ($kind === 'boundary' ? 'boundary-input' : 'data'));
        }
        foreach ($state->controls as $condition => $branch) {
            if ($id !== $condition) {
                $this->graph->connect($id, $condition, 'control', $branch);
            }
        }

        return $id;
    }

    /**
     * Reads dependencies and updates the environments of continuing paths.
     */
    public function read(Variable $node, State $state): string
    {
        assert(is_string($node->name));
        $name = '$'.$node->name;
        $id = $this->value($node, 'read', [], $state, $name);
        foreach ($state->definitions[$name] ?? [] as $definition => $_) {
            $this->graph->connect($id, $definition, 'reaching-definition');
            if ($this->graph->nodes[$definition]->kind === 'unbound') {
                $this->graph->issues[$definition] ??= new \App\Analyzer\ExperimentAnalyzer\Resolution\Issue('UNBOUND_LOCAL', $definition, 'local-read/v1', 'No local definition is known on this path.', $this->graph->nodes[$definition], ['value']);
            }
        }

        $this->graph->scalars[$id] = \App\Analyzer\ExperimentAnalyzer\Resolution\ScalarOrigins::inputs(array_keys($state->definitions[$name] ?? []), $this->graph);

        return $id;
    }

    /**
     * @param list<string> $inputs
     */
    public function write(Variable $node, array $inputs, State $state, string $kind = 'write'): string
    {
        assert(is_string($node->name));
        $name = '$'.$node->name;
        $previous = array_keys($state->definitions[$name] ?? []);
        $objects = array_filter($previous, fn (string $id): bool => !($this->graph->scalars[$id] ?? false) && $this->graph->nodes[$id]->kind !== 'unbound');
        if ($kind === 'write' && $objects !== []) {
            (new \App\Analyzer\ExperimentAnalyzer\Resolution\Boundary($this->graph))->read($node, $state, 'VALUE_LIFETIME', 'Replacing a value whose type is not proven scalar may invoke destructors or release aliased storage.');
        }
        $id = $this->value($node, $kind, $inputs, $state, $name);
        $state->definitions[$name] = [$id => true];

        return $id;
    }
}
