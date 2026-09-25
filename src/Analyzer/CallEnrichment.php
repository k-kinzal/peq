<?php

declare(strict_types=1);

namespace App\Analyzer;

use App\Analyzer\Declaration\Calls\CallSites;
use App\Analyzer\Declaration\PhpDoc\DocBlock;
use App\Analyzer\Declaration\PhpDoc\DocIndex;
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
        foreach ($graph->nodes() as $node) {
            if ($node->resolved() && $node->meta() !== null) {
                $sources->file($node->meta()->path);
            }
        }
        foreach ($graph->nodes() as $node) {
            if ($node instanceof MethodNode && $node->resolved()) {
                $syntax = $sources->callable($node);
                $block = $syntax === null ? null : DocIndex::block($syntax);
                $owner = ClassHierarchy::owner($node);
                if ($block !== null && $owner !== null) {
                    $sources->docs->blocks[DocIndex::key($node->id()->toString())] = new DocBlock($block->doc, $block->scope->inClass($owner));
                }
            }
        }
        $recorder = new BodyCallRecorder($graph, new ClassHierarchy($graph), $sources->docs);
        $sites = [];
        foreach ($graph->nodes() as $node) {
            if (($node instanceof MethodNode || $node instanceof FunctionNode) && $node->resolved()) {
                $callable = $sources->callable($node);
                $body = $callable?->getStmts();
                if ($body !== null) {
                    $scope = CallSites::of(array_values($body), $node, $node->meta()->path ?? '');
                    $sites[$node->id()->toString()] = $scope;
                    $recorder->record(array_values($body), $node, $scope, $callable);
                }
            }
        }
        CallDispatch::enrich($graph);

        return CallSites::attach($graph, $sites);
    }
}
