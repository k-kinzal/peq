<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\Flow;

use PhpParser\Node\Expr;

/**
 * A whole-variable write kills earlier definitions; an aggregate write retains them.
 */
final readonly class Assignments
{
    /**
     * Creates this value with its explicit analysis inputs.
     */
    public function __construct(private Expressions $expressions) {}

    /**
     * Evaluates the assignment inputs before replacing the target definition.
     */
    public function assign(Expr\Assign|Expr\AssignOp $node, State $state): string
    {
        if ($node instanceof Expr\AssignOp\Coalesce) {
            return (new ConditionalExpressions($this->expressions))->coalesceAssignment($node, $state);
        }
        $inputs = [];
        if ($node instanceof Expr\AssignOp) {
            $old = $this->expressions->read($node->var, $state);
            if (!$state->reachable) {
                return $old;
            }
            $inputs[] = $old;
        }
        $inputs[] = $this->expressions->read($node->expr, $state);

        return $state->reachable ? $this->write($node->var, $inputs, $state, $node instanceof Expr\AssignOp) : $inputs[array_key_last($inputs)];
    }

    /**
     * @param list<string> $inputs
     */
    public function write(Expr $target, array $inputs, State $state, bool $evaluated = false): string
    {
        if ($target instanceof Expr\Variable && is_string($target->name)) {
            return $this->expressions->recording->write($target, $inputs, $state);
        }

        return (new \App\Analyzer\ExperimentAnalyzer\Resolution\Boundary($this->expressions->recording->graph))->read($target, $state, 'INDIRECT_WRITE', 'An indirect write requires a storage model.');
    }

    /**
     * Writes the updated value, returning the old value for postfix operations.
     */
    public function increment(Expr\PostDec|Expr\PostInc|Expr\PreDec|Expr\PreInc $node, State $state): string
    {
        $old = $this->expressions->read($node->var, $state);
        if (!$state->reachable) {
            return $old;
        }
        $written = $this->write($node->var, [$old], $state, true);

        return $node instanceof Expr\PostInc || $node instanceof Expr\PostDec ? $old : $written;
    }
}
