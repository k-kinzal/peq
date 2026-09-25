<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer;

use App\Analyzer\CallBody;
use App\Analyzer\ExperimentAnalyzer\Emitter\UsageEmitter;
use App\Analyzer\Graph\Edge;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;

/**
 * Everything the body of a method reaches out to.
 *
 * Nested named declarations are analysed separately. Closure expressions remain
 * visible here for dependency discovery; the shared call-site pass assigns their
 * calls to independent lexical scopes before a consumer selects a projection.
 *
 * @visibility namespace
 */
final class BodyWalker
{
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
        $visitor = new CallBody(UsageEmitter::records(...));
        (new NodeTraverser($visitor))->traverse($body);
        foreach ($visitor->expressions as $expression) {
            array_push($relations, ...UsageEmitter::emit($expression, $scope));
        }

        return $relations;
    }
}
