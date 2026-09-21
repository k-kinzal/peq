<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\Flow;

use PhpParser\Node\Stmt;

/**
 * Computes a finite reaching-definition fixed point, including loop-carried values.
 */
final readonly class Loops
{
    /**
     * Creates this value with its explicit analysis inputs.
     */
    public function __construct(private Statements $statements) {}

    /**
     * Reads dependencies and updates the environments of continuing paths.
     */
    public function read(Stmt\Do_|Stmt\For_|Stmt\Foreach_|Stmt\While_ $node, State $state): Exits
    {
        $iterable = $this->initialize($node, $state);
        $header = clone $state;
        do {
            $previous = clone $header;
            $body = clone $header;
            $condition = $node instanceof Stmt\Do_ ? null : $this->condition($node, $body, $iterable);
            $truth = $this->truth($node);
            if ($condition !== null) {
                $body->controls[$condition] = 'iterate';
            }
            $this->iteration($node, $body, $iterable);
            $before = count($this->statements->expressions->recording->graph->nodes);
            $result = $truth === false && !$node instanceof Stmt\Do_ ? new Exits(null) : $this->statements->read($node->stmts, $body);
            $bodyNodes = array_slice(array_keys($this->statements->expressions->recording->graph->nodes), $before);
            $back = $result->continues[1] ?? [];
            if ($result->normal !== null) {
                $back[] = $result->normal;
            }
            foreach ($back as $path) {
                $test = $this->advance($node, $path, $iterable);
                $this->repeat($node, $bodyNodes, $test);
            }
            $back = array_values(array_filter($back, static fn (State $path): bool => $path->reachable));
            $header = $truth === false ? clone $state : State::join([$state, ...$back]);
        } while (!$header->sameDefinitions($previous));
        $exits = $result->breaks[1] ?? [];
        if ($truth !== true) {
            if ($node instanceof Stmt\Do_) {
                array_push($exits, ...$back);
            } else {
                $exit = clone $header;
                $this->condition($node, $exit, $iterable);
                if ($exit->reachable) {
                    $exits[] = $exit;
                }
            }
        }
        $outcome = new Exits($exits === [] ? null : State::join($exits));
        if ($outcome->normal !== null) {
            $outcome->normal->controls = $state->controls;
        }
        $outcome->absorb($result, true);

        return $outcome;
    }

    /**
     * Evaluates loop initialization once before its first header.
     */
    public function initialize(Stmt\Do_|Stmt\For_|Stmt\Foreach_|Stmt\While_ $node, State $state): ?string
    {
        if ($node instanceof Stmt\For_) {
            foreach ($node->init as $expression) {
                $this->statements->expressions->read($expression, $state);
            }
        }

        return $node instanceof Stmt\Foreach_ ? $this->statements->expressions->read($node->expr, $state) : null;
    }

    /**
     * Evaluates the loop test, returning the last condition occurrence.
     */
    public function condition(Stmt\Do_|Stmt\For_|Stmt\Foreach_|Stmt\While_ $node, State $state, ?string $iterable): ?string
    {
        if ($node instanceof Stmt\Foreach_) {
            return $iterable;
        }
        $conditions = $node instanceof Stmt\For_ ? $node->cond : [$node->cond];
        $last = null;
        foreach ($conditions as $condition) {
            $last = $this->statements->expressions->read($condition, $state);
        }

        return $last;
    }

    /**
     * Binds a foreach iteration value and key from its iterable.
     */
    public function iteration(Stmt\Do_|Stmt\For_|Stmt\Foreach_|Stmt\While_ $node, State $state, ?string $iterable): void
    {
        if ($node instanceof Stmt\Foreach_ && $iterable !== null) {
            $assignment = new Assignments($this->statements->expressions);
            $assignment->write($node->valueVar, [$iterable], $state);
            if ($node->keyVar !== null) {
                $assignment->write($node->keyVar, [$iterable], $state);
            }
        }
    }

    /**
     * Evaluates a for update or do-while test on each back edge.
     */
    public function advance(Stmt\Do_|Stmt\For_|Stmt\Foreach_|Stmt\While_ $node, State $state, ?string $iterable): ?string
    {
        if ($node instanceof Stmt\For_) {
            foreach ($node->loop as $expression) {
                $this->statements->expressions->read($expression, $state);
            }
        }
        if ($node instanceof Stmt\Do_) {
            return $this->condition($node, $state, $iterable);
        }

        return null;
    }

    /**
     * A do-while predicate controls repetition, even though the first visit is unconditional.
     *
     * @param list<string> $bodyNodes Occurrences first encountered in the loop body
     */
    public function repeat(Stmt\Do_|Stmt\For_|Stmt\Foreach_|Stmt\While_ $node, array $bodyNodes, ?string $condition): void
    {
        if ($node instanceof Stmt\Do_ && $condition !== null && Truth::of($node->cond) !== false) {
            foreach ($bodyNodes as $id) {
                $this->statements->expressions->recording->graph->connect($id, $condition, 'control', 'repeat');
            }
        }
    }

    /**
     * Classifies only syntactically constant loop conditions.
     */
    public function truth(Stmt\Do_|Stmt\For_|Stmt\Foreach_|Stmt\While_ $node): ?bool
    {
        if ($node instanceof Stmt\Foreach_) {
            return null;
        }
        if ($node instanceof Stmt\For_) {
            return $node->cond === [] ? true : Truth::of($node->cond[array_key_last($node->cond)]);
        }

        return Truth::of($node->cond);
    }
}
