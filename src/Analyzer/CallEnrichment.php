<?php

declare(strict_types=1);

namespace App\Analyzer;

use App\Analyzer\Declaration\Calls\CallSites;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Resolution\CallDispatch;
use App\Analyzer\Graph\Resolution\ClassHierarchy;

/**
 * Completes the graph before either inspection or a query chooses what to read.
 */
final class CallEnrichment
{
    /**
     * Completes receiver calls and candidate dispatches before consumers select a view.
     */
    public static function of(Graph $graph, ?int $phpVersion = null, ?PhaseCache $cache = null): Graph
    {
        $sources = new CallSources($phpVersion, $cache);
        $recorder = new BodyCallRecorder($graph, new ClassHierarchy($graph));
        $sites = [];
        foreach ($graph->nodes() as $node) {
            if (($node instanceof MethodNode || $node instanceof FunctionNode) && $node->resolved()) {
                $body = $sources->callable($node)?->getStmts();
                if ($body !== null) {
                    $scope = CallSites::of(array_values($body), $node, $node->meta()->path ?? '');
                    $sites[$node->id()->toString()] = $scope;
                    $recorder->record(array_values($body), $node, $scope);
                }
            }
        }
        CallDispatch::enrich($graph);

        return CallSites::attach($graph, $sites);
    }
}
