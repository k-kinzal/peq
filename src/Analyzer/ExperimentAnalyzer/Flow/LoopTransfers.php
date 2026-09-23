<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\Flow;

use App\Analyzer\ExperimentAnalyzer\Resolution\Boundary;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Stmt;

/**
 * Separates loop initialization, tests, updates and numeric abrupt exits.
 */
final readonly class LoopTransfers
{
    /**
     * Uses the enclosing callable's expression evaluator.
     */
    public function __construct(private Expressions $expressions) {}

    /**
     * Reads every for-test expression; only its last value selects the body.
     */
    public function condition(Stmt\Do_|Stmt\For_|Stmt\Foreach_|Stmt\While_ $node, State $state, ?string $iterable): string
    {
        if ($node instanceof Stmt\Foreach_) {
            (new Boundary($this->expressions->recording->graph))->read($node->expr, $state, 'FOREACH_PROTOCOL', 'Iterator callbacks, element types and reference-bearing arrays are not solved; the body and exits retain their structural dependencies.');

            return $this->expressions->recording->value($node->expr, 'iteration-test', $iterable === null ? [] : [$iterable], $state);
        }
        $last = null;
        foreach ($node instanceof Stmt\For_ ? $node->cond : [$node->cond] as $condition) {
            $last = $this->expressions->read($condition, $state);
        }
        if ($last !== null) {
            (new \App\Analyzer\ExperimentAnalyzer\Resolution\Conditions())->observe($last, $this->expressions->recording->graph);
        }

        return $last ?? $this->expressions->recording->value($node, 'unconditional-loop', [], $state);
    }

    /**
     * Classifies only syntactically constant tests, never a guessed iteration count.
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

    /**
     * Assigns the current key and value while retaining unresolved iterator effects.
     */
    public function bind(Stmt\Foreach_ $node, State $state, string $condition): void
    {
        $element = $this->expressions->recording->value($node, 'iteration-value', [$condition], $state);
        $this->expressions->recording->graph->scalars[$element] = false;
        foreach ([$node->keyVar, $node->valueVar] as $target) {
            if ($target !== null) {
                (new Assignments($this->expressions))->write($target, [$element], $state);
            }
        }
    }

    /**
     * Stops the current block and carries the requested level to its loop owner.
     */
    public function jump(Stmt\Break_|Stmt\Continue_ $node, State $state): Exits
    {
        if ($node->num !== null && (!$node->num instanceof Int_ || $node->num->value < 1)) {
            (new Boundary($this->expressions->recording->graph))->read($node, $state, 'DYNAMIC_JUMP', 'The loop exit level is not a positive integer literal.');

            return new Exits($state);
        }
        $level = $node->num->value ?? 1;
        $id = $this->expressions->recording->value($node, $node instanceof Stmt\Break_ ? 'break' : 'continue', [], $state);
        $state->controls[$id] = 'taken';
        $exits = new Exits(null);
        if ($node instanceof Stmt\Break_) {
            $exits->breaks[$level][] = $state;
        } else {
            $exits->continues[$level][] = $state;
        }

        return $exits;
    }
}
