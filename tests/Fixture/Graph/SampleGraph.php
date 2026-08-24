<?php

declare(strict_types=1);

namespace Tests\Fixture\Graph;

use App\Analyzer\Graph\Edge\DeclarationMethodEdge;
use App\Analyzer\Graph\Edge\MethodCallEdge;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;

/**
 * Small graphs of an imagined codebase, shaped for a specific question.
 *
 * A reporter or a traversal is read by walking a graph of a known shape, and stating
 * that shape inline in every test buries the one line each of them is about. Each
 * builder here names the shape it produces, so a test can say which shape it needs
 * and then assert only what it is checking.
 */
final class SampleGraph
{
    /**
     * A class declaring two methods, one of which calls into another class.
     *
     * `App\Domain\Invoice` declares `total` and `lines`; `total` calls
     * `App\Domain\Money::add`.
     *
     * @return Graph The graph of that codebase
     */
    public static function invoice(): Graph
    {
        $meta = SampleEdges::meta();
        $invoice = new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, $meta);
        $total = new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true, $meta);
        $lines = new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'lines'), true, $meta);
        $add = new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true, $meta);

        $graph = new Graph();
        $graph->addNodes([$invoice, $total, $lines, $add]);
        $graph->addEdges([
            new DeclarationMethodEdge($invoice, $total, $meta),
            new DeclarationMethodEdge($invoice, $lines, $meta),
            new MethodCallEdge($total, $add, $meta),
        ]);

        return $graph;
    }

    /**
     * Two methods calling each other.
     *
     * `App\Domain\Invoice::total` calls `App\Domain\Money::add`, which calls back.
     *
     * @return Graph The graph of that codebase
     */
    public static function cyclic(): Graph
    {
        $meta = SampleEdges::meta();
        $total = new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true, $meta);
        $add = new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true, $meta);

        $graph = new Graph();
        $graph->addNodes([$total, $add]);
        $graph->addEdges([
            new MethodCallEdge($total, $add, $meta),
            new MethodCallEdge($add, $total, $meta),
        ]);

        return $graph;
    }

    /**
     * Two methods that both call the same third one.
     *
     * @return Graph The graph of that codebase
     */
    public static function sharedDependency(): Graph
    {
        $meta = SampleEdges::meta();
        $invoice = new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, $meta);
        $total = new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true, $meta);
        $lines = new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'lines'), true, $meta);
        $add = new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true, $meta);

        $graph = new Graph();
        $graph->addNodes([$invoice, $total, $lines, $add]);
        $graph->addEdges([
            new DeclarationMethodEdge($invoice, $total, $meta),
            new DeclarationMethodEdge($invoice, $lines, $meta),
            new MethodCallEdge($total, $add, $meta),
            new MethodCallEdge($lines, $add, $meta),
        ]);

        return $graph;
    }
}
