<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\Flow;

use App\Analyzer\ExperimentAnalyzer\Resolution\Boundary;
use App\Analyzer\ExperimentAnalyzer\Resolution\Guards;
use App\Analyzer\ExperimentAnalyzer\Resolution\Repetition;
use PhpParser\Node\Stmt;

/**
 * Solves nonrepeating loops and preserves explicit control around unresolved recurrence.
 */
final readonly class Loops
{
    /**
     * Reuses the callable's statement rules within each loop body.
     */
    public function __construct(private Statements $statements) {}

    /**
     * Evaluates initialization once, then distinguishes a single visit from recurrence.
     */
    public function read(Stmt\Do_|Stmt\For_|Stmt\Foreach_|Stmt\While_ $node, State $state): Exits
    {
        $expressions = $this->statements->expressions;
        $graph = $expressions->recording->graph;
        if ($node instanceof Stmt\Foreach_ && $node->byRef) {
            (new Boundary($graph))->read($node, $state, 'FOREACH_REFERENCE', 'Reference iteration creates persistent aliases; its storage effects are not solved.');

            return new Exits($state);
        }
        foreach ($node instanceof Stmt\For_ ? $node->init : [] as $initial) {
            $expressions->read($initial, $state);
        }
        $iterable = $node instanceof Stmt\Foreach_ ? $expressions->read($node->expr, $state) : null;
        $trial = clone $graph;
        $solver = new self(new Statements(new Expressions(new Recording($trial))));
        [$outcome, $back] = $solver->pass($node, clone $state, $iterable);
        if ($back === []) {
            $graph->adopt($trial);

            return $outcome;
        }
        $recurrence = new Repetition($graph);
        $inputs = $recurrence->open($node, $state, $back);
        [$outcome, $back] = $this->pass($node, $state, $iterable);
        $recurrence->close($node, $inputs, $back);

        return $outcome;
    }

    /**
     * Evaluates one symbolic visit, preserving every break, continue and test exit.
     *
     * @return array{Exits, list<State>}
     */
    public function pass(Stmt\Do_|Stmt\For_|Stmt\Foreach_|Stmt\While_ $node, State $state, ?string $iterable): array
    {
        $transfers = new LoopTransfers($this->statements->expressions);
        $truth = $transfers->truth($node);
        $exits = [];
        if (!$node instanceof Stmt\Do_) {
            $condition = $transfers->condition($node, $state, $iterable);
            if ($truth !== true) {
                $exit = clone $state;
                $exit->controls[$condition] = $node instanceof Stmt\Foreach_ ? 'exhausted' : 'falsy';
                $exits[] = $exit;
            }
            if ($truth === false) {
                return [new Exits($exits[0]), []];
            }
            $state->controls[$condition] = $node instanceof Stmt\Foreach_ ? 'iterate' : 'truthy';
            if ($node instanceof Stmt\Foreach_) {
                $transfers->bind($node, $state, $condition);
            }
        }
        $body = $this->statements->read($node->stmts, $state);
        $back = $body->continues[1] ?? [];
        if ($body->normal !== null) {
            $back[] = $body->normal;
        }
        [$back, $tests] = $this->advance($node, $back, $iterable);
        array_push($exits, ...($body->breaks[1] ?? []), ...$tests);
        $result = new Exits($exits === [] ? null : (new Guards())->join($node, $exits, $this->statements->expressions->recording->graph));
        $result->absorb($body, true);

        return [$result, $back];
    }

    /**
     * Continue runs for-updates and do-tests; break and return do neither.
     *
     * @param list<State> $paths
     *
     * @return array{list<State>, list<State>}
     */
    public function advance(Stmt\Do_|Stmt\For_|Stmt\Foreach_|Stmt\While_ $node, array $paths, ?string $iterable): array
    {
        $transfers = new LoopTransfers($this->statements->expressions);
        $exits = [];
        foreach ($paths as $path) {
            foreach ($node instanceof Stmt\For_ ? $node->loop : [] as $update) {
                $this->statements->expressions->read($update, $path);
            }
            if ($node instanceof Stmt\Do_) {
                $condition = $transfers->condition($node, $path, $iterable);
                if ($transfers->truth($node) !== true) {
                    $exit = clone $path;
                    $exit->controls[$condition] = 'falsy';
                    $exits[] = $exit;
                }
                $path->controls[$condition] = 'truthy';
            }
        }

        return [$transfers->truth($node) === false ? [] : $paths, $exits];
    }
}
