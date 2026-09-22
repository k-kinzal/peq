<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\Resolution;

use App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph;
use App\Analyzer\ExperimentAnalyzer\Flow\State;
use PhpParser\Node;

/**
 * Unknown transfers retain incoming evidence and poison their possible continuation.
 */
final readonly class Boundary
{
    /**
     * Creates a transfer recorder for one independent dependency graph.
     */
    public function __construct(private DependencyGraph $graph) {}

    /**
     * Preserves an opaque region without pretending to execute its children.
     */
    public function read(Node $node, State $state, string $code, string $reason): string
    {
        $id = $this->graph->record($node, 'unknown');
        $this->graph->issues[$id] = new Issue($code, $id, 'checked-rules/v1', $reason, $this->graph->nodes[$id]);
        foreach ($state->definitions as $definitions) {
            foreach ($definitions as $definition => $_) {
                $this->graph->connect($id, $definition, 'possible-input');
            }
        }
        foreach ($state->controls as $condition => $branch) {
            $this->graph->connect($id, $condition, 'control', $branch);
        }
        $this->contents($node, $id);
        $this->implicit($node, $id);
        foreach ($state->definitions as $name => $definitions) {
            $written = $this->graph->record($node, 'unknown-write', $name);
            $this->graph->connect($written, $id, 'unknown-effect');
            $state->definitions[$name] = array_filter($definitions, fn (bool $present, string $definition): bool => $this->graph->nodes[$definition]->kind !== 'unknown-write', ARRAY_FILTER_USE_BOTH) + [$written => true];
        }
        $state->controls = array_filter($state->controls, static fn (string $branch): bool => $branch !== 'unknown-continuation');
        $continuation = $this->graph->record($node, 'unknown-continuation');
        $this->graph->connect($continuation, $id, 'control', 'unknown-continuation');
        $state->controls[$continuation] = 'unknown-continuation';

        return $id;
    }

    /**
     * Keeps source occurrences selectable even inside an unsupported region.
     */
    public function contents(Node $node, string $boundary): void
    {
        foreach ($this->graph->inventory->sites ?? [] as $site) {
            $source = $site->source;
            if ($site->start < $node->getStartFilePos() || $site->end > $node->getEndFilePos()) {
                continue;
            }
            $this->graph->nodes[$source->id] = $source;
            $this->graph->connect($source->id, $boundary, 'unresolved-region');
            $this->graph->connect($boundary, $source->id, 'possible-input');
        }
    }

    /**
     * Keeps literal compact names selectable as possible, unresolved local reads.
     */
    public function implicit(Node $node, string $boundary): void
    {
        if (!$node instanceof Node\Expr\FuncCall || !$node->name instanceof Node\Name || strtolower($node->name->getLast()) !== 'compact') {
            return;
        }
        foreach ($node->getRawArgs() as $argument) {
            if ($argument instanceof Node\Arg && $argument->value instanceof Node\Scalar\String_) {
                $id = $this->graph->record($argument->value, 'possible-implicit-read', '$'.$argument->value->value);
                $this->graph->connect($id, $boundary, 'unresolved-region');
                $this->graph->connect($boundary, $id, 'possible-input');
            }
        }
    }
}
