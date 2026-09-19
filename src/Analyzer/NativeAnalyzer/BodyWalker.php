<?php

declare(strict_types=1);

namespace App\Analyzer\NativeAnalyzer;

use App\Analyzer\Graph\Edge;
use App\Analyzer\NativeAnalyzer\Emitter\UsageEmitter;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

/**
 * Everything the body of a method reaches out to.
 *
 * A body is read as one region rather than as a nest of scopes: what a closure
 * written inside a method reaches is reached by that method, and so is what an anonymous
 * class written inside it reaches, because neither of them is a symbol an impact
 * analysis can report. Every expression found anywhere in the body is therefore
 * attributed to the method the body belongs to.
 *
 * @visibility namespace
 */
final class BodyWalker
{
    /**
     * The finder that locates the expressions a body writes, reused across bodies.
     */
    private readonly NodeFinder $finder;

    /**
     * Prepares a walk over method bodies.
     */
    public function __construct()
    {
        $this->finder = new NodeFinder();
    }

    /**
     * Records the relations written inside one method body.
     *
     * @param list<Stmt>    $body  The statements of the body, as they are written
     * @param AnalysisScope $scope The method the body belongs to
     *
     * @return list<Edge> The relations the body writes
     */
    public function relations(array $body, AnalysisScope $scope): array
    {
        $relations = [];
        foreach ($this->finder->find($body, UsageEmitter::records(...)) as $expression) {
            array_push($relations, ...UsageEmitter::emit($expression, $scope));
        }

        return $relations;
    }
}
