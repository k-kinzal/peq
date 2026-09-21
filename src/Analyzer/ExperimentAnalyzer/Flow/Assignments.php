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
            $inputs[] = $this->expressions->read($node->var, $state);
        }
        $inputs[] = $this->expressions->read($node->expr, $state);

        return $state->reachable ? $this->write($node->var, $inputs, $state) : $inputs[array_key_last($inputs)];
    }

    /**
     * @param list<string> $inputs
     */
    public function write(Expr $target, array $inputs, State $state): string
    {
        if ($target instanceof Expr\Variable) {
            return $this->expressions->recording->write($target, $inputs, $state);
        }
        if ($target instanceof Expr\List_ || $target instanceof Expr\Array_) {
            $id = $this->expressions->recording->value($target, 'destructure', $inputs, $state);
            foreach ($target->items as $item) {
                if ($item !== null) {
                    $keys = $item->key === null ? [] : [$this->expressions->read($item->key, $state)];
                    $this->write($item->value, [$id, ...$keys], $state);
                }
            }

            return $id;
        }
        $root = $target;
        while ($root instanceof Expr\ArrayDimFetch) {
            if ($root->dim !== null) {
                $inputs[] = $this->expressions->read($root->dim, $state);
            }
            $root = $root->var;
        }
        if ($root instanceof Expr\Variable) {
            $inputs[] = $this->expressions->read($root, $state);
            $this->expressions->recording->graph->diagnose($target, 'Array boundary: elements are tracked together; aggregate dependencies are possible dependencies.');

            return $this->expressions->recording->write($root, $inputs, $state, 'aggregate-write');
        }
        $inputs[] = $this->expressions->read($target, $state);

        return $this->expressions->recording->value($target, 'heap-write', $inputs, $state);
    }

    /**
     * Writes the updated value, returning the old value for postfix operations.
     */
    public function increment(Expr\PostDec|Expr\PostInc|Expr\PreDec|Expr\PreInc $node, State $state): string
    {
        $old = $this->expressions->read($node->var, $state);
        $written = $this->write($node->var, [$old], $state);

        return $node instanceof Expr\PostInc || $node instanceof Expr\PostDec ? $old : $written;
    }
}
