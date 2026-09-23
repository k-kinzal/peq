<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\Flow;

use PhpParser\Node\Stmt;

/**
 * Each elseif sees only paths for which preceding conditions were false.
 */
final readonly class Branches
{
    /**
     * Creates this value with its explicit analysis inputs.
     */
    public function __construct(private Statements $statements) {}

    /**
     * Reads dependencies and updates the environments of continuing paths.
     */
    public function read(Stmt\If_ $node, State $state): Exits
    {
        $outcome = new Exits(null);
        $normals = [];
        $remaining = $state;
        foreach ([$node, ...$node->elseifs] as $branch) {
            if ($remaining === null) {
                break;
            }
            $condition = $this->statements->expressions->read($branch->cond, $remaining);
            if (!$remaining->reachable) {
                $remaining = null;

                break;
            }
            (new \App\Analyzer\ExperimentAnalyzer\Resolution\Conditions())->observe($condition, $this->statements->expressions->recording->graph);
            $truth = Truth::of($branch->cond);
            if ($truth !== false) {
                $yes = clone $remaining;
                $yes->controls[$condition] = 'truthy';
                $result = $this->statements->read($branch->stmts, $yes);
                $outcome->absorb($result);
                if ($result->normal !== null) {
                    $normals[] = $result->normal;
                }
            }
            $remaining->controls[$condition] = 'falsy';
            if ($truth === true) {
                $remaining = null;
            }
        }
        if ($remaining !== null) {
            $result = $this->statements->read($node->else->stmts ?? [], $remaining);
            $outcome->absorb($result);
            if ($result->normal !== null) {
                $normals[] = $result->normal;
            }
        }
        $outcome->normal = $normals === [] ? null : (new \App\Analyzer\ExperimentAnalyzer\Resolution\Guards())->join($node, $normals, $this->statements->expressions->recording->graph);

        return $outcome;
    }
}
