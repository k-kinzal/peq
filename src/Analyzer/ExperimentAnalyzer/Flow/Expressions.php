<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\Flow;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;

/**
 * Evaluates expression dependencies in PHP's defined assignment/branch order.
 */
final readonly class Expressions
{
    /**
     * Creates this value with its explicit analysis inputs.
     */
    public function __construct(public Recording $recording) {}

    /**
     * Reads dependencies and updates the environments of continuing paths.
     */
    public function read(Expr $node, State $state): string
    {
        [$rule, $reason] = (new \App\Analyzer\ExperimentAnalyzer\Resolution\Rules())->classify($node);
        if ($reason !== '') {
            return (new \App\Analyzer\ExperimentAnalyzer\Resolution\Boundary($this->recording->graph))->read($node, $state, $rule, $reason);
        }

        if (\App\Analyzer\ExperimentAnalyzer\Resolution\ScalarOrigins::effects($node, $state, $this->recording->graph)) {
            return (new \App\Analyzer\ExperimentAnalyzer\Resolution\Boundary($this->recording->graph))->read($node, $state, 'OPERAND_TYPES', 'Operand types are not proven scalar; implicit object conversions, operator behavior or heap comparisons are not modeled.');
        }
        if ($node instanceof Expr\Variable) {
            return $this->recording->read($node, $state);
        }
        if ($node instanceof Expr\Assign || $node instanceof Expr\AssignOp) {
            return (new Assignments($this))->assign($node, $state);
        }
        if ($node instanceof Expr\PreInc || $node instanceof Expr\PreDec || $node instanceof Expr\PostInc || $node instanceof Expr\PostDec) {
            return (new Assignments($this))->increment($node, $state);
        }
        if ($node instanceof Expr\Ternary || ($node instanceof Expr\BinaryOp && in_array($node::class, [Expr\BinaryOp\BooleanAnd::class, Expr\BinaryOp\BooleanOr::class, Expr\BinaryOp\LogicalAnd::class, Expr\BinaryOp\LogicalOr::class, Expr\BinaryOp\Coalesce::class], true))
        ) {
            return (new ConditionalExpressions($this))->read($node, $state);
        }

        return $this->boundary($node, $state);
    }

    /**
     * Reads a branch value and omits it when evaluating that branch terminates.
     *
     * @return list<string>
     */
    public function values(Expr $node, State $state): array
    {
        $value = $this->read($node, $state);

        return $state->reachable ? [$value] : [];
    }

    /**
     * Records the inputs of an expression admitted by the pure-expression rule.
     */
    public function boundary(Expr $node, State $state): string
    {
        return $this->recording->value($node, $node instanceof Scalar || $node instanceof Expr\ConstFetch ? 'literal' : 'expression', $this->children($node, $state), $state);
    }

    /**
     * @return list<string>
     */
    public function children(Node $node, State $state): array
    {
        $inputs = [];
        foreach ($node->getSubNodeNames() as $name) {
            $children = (get_object_vars($node)[$name] ?? null);
            foreach (is_array($children) ? $children : [$children] as $child) {
                if (!$state->reachable) {
                    break;
                }
                if ($child instanceof Expr) {
                    $inputs[] = $this->read($child, $state);
                } elseif ($child instanceof Node && !$child instanceof Node\Stmt && !$child instanceof Node\FunctionLike) {
                    array_push($inputs, ...$this->children($child, $state));
                }
            }
        }

        return $inputs;
    }
}
