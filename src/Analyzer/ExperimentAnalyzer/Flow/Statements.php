<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\Flow;

use App\Analyzer\ExperimentAnalyzer\DataFlow\InspectionException;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar\Int_;
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
     *
     * @throws InspectionException If the requested analysis or encoding is rejected
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
     *
     * @throws InspectionException If the requested analysis or encoding is rejected
     */
    public function statement(Stmt $node, State $state): Exits
    {
        if ($node instanceof Stmt\If_) {
            return (new Branches($this))->read($node, $state);
        }
        if ($node instanceof Stmt\For_ || $node instanceof Stmt\Foreach_ || $node instanceof Stmt\While_ || $node instanceof Stmt\Do_) {
            return (new Loops($this))->read($node, $state);
        }
        if ($node instanceof Stmt\Switch_) {
            return (new Switches($this))->read($node, $state);
        }
        if ($node instanceof Stmt\Return_) {
            $inputs = $node->expr === null ? [] : [$this->expressions->read($node->expr, $state)];
            if ($state->reachable) {
                $this->expressions->recording->value($node, 'return', $inputs, $state);
            }

            return new Exits(null);
        }
        if ($node instanceof Stmt\Break_ || $node instanceof Stmt\Continue_) {
            $result = new Exits(null);
            $depth = $node->num instanceof Int_ ? $node->num->value : 1;
            if ($node instanceof Stmt\Break_) {
                $result->breaks[$depth] = [$state];
            } else {
                $result->continues[$depth] = [$state];
            }

            return $result;
        }
        if ($node instanceof Stmt\Expression) {
            $this->expressions->read($node->expr, $state);

            return new Exits($state->reachable ? $state : null);
        }

        return $this->simple($node, $state);
    }

    /**
     * Records output and unset statements, rejecting unsupported statements.
     *
     * @throws InspectionException If the requested analysis or encoding is rejected
     */
    public function simple(Stmt $node, State $state): Exits
    {
        if ($node instanceof Stmt\Echo_) {
            $inputs = [];
            foreach ($node->exprs as $expression) {
                $inputs[] = $this->expressions->read($expression, $state);
            }
            $this->expressions->recording->value($node, 'output', $inputs, $state);
        } elseif ($node instanceof Stmt\Unset_) {
            foreach ($node->vars as $variable) {
                if ($variable instanceof Expr\Variable) {
                    $this->expressions->recording->write($variable, [], $state, 'undefined');
                } else {
                    (new Assignments($this->expressions))->write($variable, [], $state);
                }
            }
        } elseif (!$node instanceof Stmt\Nop) {
            throw new InspectionException(sprintf('Cannot inspect %s at line %d: unsupported statement %s.', $this->expressions->recording->graph->target, $node->getStartLine(), $node->getType()));
        }

        return new Exits($state);
    }
}
