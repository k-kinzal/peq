<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\Flow;

use PhpParser\Node\Expr;

/**
 * Forks environments before evaluating expressions that PHP may skip.
 */
final readonly class ConditionalExpressions
{
    /**
     * Creates this value with its explicit analysis inputs.
     */
    public function __construct(private Expressions $expressions) {}

    /**
     * Reads dependencies and updates the environments of continuing paths.
     */
    public function read(Expr\BinaryOp|Expr\Ternary $node, State $state): string
    {
        $test = $node instanceof Expr\Ternary ? $node->cond : $node->left;
        $condition = $this->expressions->read($test, $state);
        $truth = Truth::of($test);
        $yes = clone $state;
        $no = clone $state;
        $yes->controls[$condition] = 'truthy';
        $no->controls[$condition] = 'falsy';
        $inputs = [$condition];
        $inputs = $node instanceof Expr\Ternary
            ? $this->ternary($node, $state, $yes, $no, $truth, $inputs)
            : $this->binary($node, $state, $yes, $no, $truth, $condition, $inputs);
        $state->continueWith([$yes, $no]);

        return $this->expressions->recording->value($node, 'expression', $inputs, $state);
    }

    /**
     * Evaluates only reachable ternary arms in independent environments.
     *
     * @param list<string> $inputs
     *
     * @return list<string>
     */
    public function ternary(Expr\Ternary $node, State $state, State $yes, State $no, ?bool $truth, array $inputs): array
    {
        $yes->reachable = $state->reachable && $truth !== false;
        $no->reachable = $state->reachable && $truth !== true;
        if ($yes->reachable && $node->if !== null) {
            array_push($inputs, ...$this->expressions->values($node->if, $yes));
        }
        if ($no->reachable) {
            array_push($inputs, ...$this->expressions->values($node->else, $no));
        }

        return $inputs;
    }

    /**
     * Evaluates a short-circuit operand only on the path that requires it.
     *
     * @param list<string> $inputs
     *
     * @return list<string>
     */
    public function binary(Expr\BinaryOp $node, State $state, State $yes, State $no, ?bool $truth, string $condition, array $inputs): array
    {
        $test = $node->left;
        $isOr = $node instanceof Expr\BinaryOp\BooleanOr || $node instanceof Expr\BinaryOp\LogicalOr;
        if ($node instanceof Expr\BinaryOp\Coalesce) {
            $yes->controls[$condition] = 'null-or-unset';
            $no->controls[$condition] = 'non-null';
            $truth = $test instanceof Expr\ConstFetch && strtolower($test->name->toString()) === 'null' ? true : null;
        }
        $evaluated = $isOr ? $no : $yes;
        $skipped = $isOr ? $yes : $no;
        $evaluated->reachable = $state->reachable && ($isOr ? $truth !== true : $truth !== false);
        $skipped->reachable = $state->reachable && ($isOr ? $truth !== false : $truth !== true);
        if ($evaluated->reachable) {
            array_push($inputs, ...$this->expressions->values($node->right, $evaluated));
        }

        return $inputs;
    }

    /**
     * Joins the existing value with the conditional assignment path.
     */
    public function coalesceAssignment(Expr\AssignOp\Coalesce $node, State $state): string
    {
        $condition = $this->expressions->read($node->var, $state);
        $assigned = clone $state;
        $assigned->controls[$condition] = 'null-or-unset';
        $value = $this->expressions->read($node->expr, $assigned);
        $inputs = [$condition];
        if ($assigned->reachable) {
            $inputs[] = (new Assignments($this->expressions))->write($node->var, [$value], $assigned);
        }
        $skipped = clone $state;
        $skipped->controls[$condition] = 'non-null';
        $state->continueWith([$skipped, $assigned]);

        return $this->expressions->recording->value($node, 'expression', $inputs, $state);
    }

    /**
     * Keeps arm bodies independent and joins only paths that return a value.
     */
    public function match(Expr\Match_ $node, State $state): string
    {
        $subject = $this->expressions->read($node->cond, $state);
        $remaining = clone $state;
        $states = [];
        $inputs = [$subject];
        $default = null;
        foreach ($node->arms as $arm) {
            if ($arm->conds === null) {
                $default = $arm;

                continue;
            }
            $matched = [];
            foreach ($arm->conds as $condition) {
                $value = $this->expressions->read($condition, $remaining);
                $test = $this->expressions->recording->value($condition, 'condition', [$subject, $value], $remaining);
                $branch = clone $remaining;
                $branch->controls[$test] = 'match';
                $matched[] = $branch;
                $remaining->controls[$test] = 'no-match';
            }
            if ($matched !== []) {
                $branch = State::join($matched);
                $branch->controls[$subject] = 'matched-arm';
                $value = $this->expressions->read($arm->body, $branch);
                if ($branch->reachable) {
                    $inputs[] = $value;
                }
                $states[] = $branch;
            }
        }
        if ($default !== null) {
            $value = $this->expressions->read($default->body, $remaining);
            if ($remaining->reachable) {
                $inputs[] = $value;
            }
            $states[] = $remaining;
        }
        $state->continueWith($states);

        return $this->expressions->recording->value($node, 'expression', $inputs, $state);
    }
}
