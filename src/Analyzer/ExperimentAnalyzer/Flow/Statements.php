<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\Flow;

use PhpParser\Node\Stmt;

/**
 * Structured control flow keeps abrupt exits out of the next statement.
 */
final readonly class Statements
{
    /**
     * Creates this value with its explicit analysis inputs.
     */
    public function __construct(public Expressions $expressions) {}

    /**
     * @param array<Stmt> $nodes
     */
    public function read(array $nodes, State $state): Exits
    {
        $result = new Exits($state->reachable ? $state : null);
        foreach ($nodes as $node) {
            if ($result->normal === null) {
                break;
            }
            $next = $this->statement($node, $result->normal);
            $result->absorb($next);
            $result->normal = $next->normal;
        }

        return $result;
    }

    /**
     * Dispatches one statement and preserves its abrupt exits.
     */
    public function statement(Stmt $node, State $state): Exits
    {
        [$rule, $reason] = (new \App\Analyzer\ExperimentAnalyzer\Resolution\Rules())->classify($node);
        if ($reason !== '') {
            (new \App\Analyzer\ExperimentAnalyzer\Resolution\Boundary($this->expressions->recording->graph))->read($node, $state, $rule, $reason);

            return new Exits($state);
        }

        if ($node instanceof Stmt\If_) {
            return (new Branches($this))->read($node, $state);
        }
        if ($node instanceof Stmt\While_ || $node instanceof Stmt\Do_ || $node instanceof Stmt\For_ || $node instanceof Stmt\Foreach_) {
            return (new Loops($this))->read($node, $state);
        }
        if ($node instanceof Stmt\Break_ || $node instanceof Stmt\Continue_) {
            return (new LoopTransfers($this->expressions))->jump($node, $state);
        }
        if ($node instanceof Stmt\Return_) {
            $inputs = $node->expr === null ? [] : [$this->expressions->read($node->expr, $state)];
            if ($state->reachable) {
                $this->expressions->recording->value($node, 'return', $inputs, $state);
            }

            return new Exits(null);
        }
        if ($node instanceof Stmt\Expression) {
            $this->expressions->read($node->expr, $state);

            return new Exits($state->reachable ? $state : null);
        }

        return $this->simple($node, $state);
    }

    /**
     * Records output inputs or an admitted no-op statement.
     */
    public function simple(Stmt $node, State $state): Exits
    {
        if ($node instanceof Stmt\Echo_) {
            $unsafe = array_filter($node->exprs, fn (\PhpParser\Node\Expr $expression): bool => !\App\Analyzer\ExperimentAnalyzer\Resolution\ScalarOrigins::expression($expression, $state, $this->expressions->recording->graph));
            if ($unsafe !== []) {
                (new \App\Analyzer\ExperimentAnalyzer\Resolution\Boundary($this->expressions->recording->graph))->read($node, $state, 'OUTPUT_CONVERSION', 'Output conversion may invoke __toString(); scalar operands have not been proved.');

                return new Exits($state);
            }
            $inputs = [];
            foreach ($node->exprs as $expression) {
                $inputs[] = $this->expressions->read($expression, $state);
                if (!$state->reachable) {
                    return new Exits(null);
                }
            }
            $this->expressions->recording->value($node, 'output', $inputs, $state);
        }

        return new Exits($state);
    }
}
