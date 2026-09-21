<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\Flow;

use App\Analyzer\ExperimentAnalyzer\Invocation\CallEffects;
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
    public function __construct(public Recording $recording, private CallEffects $calls = new CallEffects()) {}

    /**
     * Reads dependencies and updates the environments of continuing paths.
     */
    public function read(Expr $node, State $state): string
    {
        if ($node instanceof Expr\Throw_ || $node instanceof Expr\Exit_) {
            $id = $this->recording->value($node, 'termination', $this->children($node, $state), $state);
            $state->reachable = false;

            return $id;
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
        if ($node instanceof Expr\Match_) {
            return (new ConditionalExpressions($this))->match($node, $state);
        }
        if ($node instanceof Expr\Closure || $node instanceof Expr\ArrowFunction) {
            return (new Captures($this))->read($node, $state);
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
     * Records opaque calls/heap reads or ordinary expression inputs.
     */
    public function boundary(Expr $node, State $state): string
    {
        if ($node instanceof Expr\FuncCall || $node instanceof Expr\MethodCall || $node instanceof Expr\StaticCall || $node instanceof Expr\New_) {
            $this->recording->graph->diagnose($node, 'Call boundary: arguments/receiver are inputs; return values and heap effects are not followed; known reference outputs are recorded.');

            $inputs = $this->calls->inputs($node, $this, $state);
            $id = $this->recording->value($node, $state->reachable ? 'call' : 'aborted-call', $inputs, $state);
            if ($state->reachable) {
                $this->calls->apply($node, $id, $this, $state);
            }

            return $id;
        }
        if ($node instanceof Expr\PropertyFetch || $node instanceof Expr\StaticPropertyFetch) {
            $this->recording->graph->diagnose($node, 'Heap boundary: property values and object aliases are not followed.');

            return $this->recording->value($node, 'boundary', $this->children($node, $state), $state);
        }

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
