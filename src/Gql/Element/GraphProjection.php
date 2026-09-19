<?php

declare(strict_types=1);

namespace App\Gql\Element;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\NodeDatum;

/**
 * Turns the graph peq analysed into the graph a query can be written against.
 *
 * The two are the same graph seen from different sides. peq's own is built for
 * walking away from a symbol and back towards it; a query's is built for matching
 * patterns, which means labels to select by, properties to compare, and adjacency in
 * both directions.
 *
 * Doing the turning once, up front, is deliberate. A query may match a pattern
 * against every symbol in a codebase, and working out a symbol's labels each time it
 * is considered would make the cost of a pattern depend on how often it fails.
 *
 * @visibility App\Gql
 */
final class GraphProjection
{
    /**
     * Returns the analysed graph as a query sees it.
     *
     * @param Graph $graph The graph analysis produced
     *
     * @example Every analysed symbol becomes a symbol a pattern can match
     *     $graph = new \App\Analyzer\Graph\Graph();
     *     $graph->addNode(new \App\Analyzer\Graph\Node\ClassNode(\App\Analyzer\Graph\NodeId\ClassNodeId::of('App\\Invoice'), true));
     *     count(\App\Gql\Element\GraphProjection::of($graph)->nodes()) // => 1
     * @example A relation is reachable from both of its ends
     *     $graph = new \App\Analyzer\Graph\Graph();
     *     $meta = new \App\Analyzer\Graph\FileMeta('/project/src/Invoice.php', 12, 1);
     *     $caller = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('App\\Invoice', 'total'), true);
     *     $called = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('App\\Money', 'add'), true);
     *     $graph->addEdge(new \App\Analyzer\Graph\Edge\Usage\MethodCallEdge($caller, $called, $meta));
     *     count(\App\Gql\Element\GraphProjection::of($graph)->arriving('App\\Money::add')) // => 1
     *
     * @return ElementGraph The graph a query is written against
     */
    public static function of(Graph $graph): ElementGraph
    {
        $nodes = [];
        foreach ($graph->nodes() as $node) {
            $nodes[$node->id()->toString()] = self::node($node);
        }

        $leaving = [];
        $arriving = [];
        foreach ($graph->authoredEdges() as $edge) {
            $datum = self::edge($edge);
            $leaving[$datum->origin][] = $datum;
            $arriving[$datum->target][] = $datum;
        }

        return new ElementGraph($nodes, $leaving, $arriving);
    }

    /**
     * Returns one symbol as a query sees it.
     *
     * @param Node $node The analysed symbol
     *
     * @example A symbol carries the labels a pattern selects it by
     *     $named = \App\Analyzer\Graph\NodeId\ClassNodeId::of('App\\Invoice');
     *     \App\Gql\Element\GraphProjection::node(new \App\Analyzer\Graph\Node\ClassNode($named, true))->labels // => ['Class', 'ClassLike', 'Resolved']
     *
     * @return NodeDatum The symbol
     */
    public static function node(Node $node): NodeDatum
    {
        return new NodeDatum(
            $node->id()->toString(),
            NodeLabels::of($node),
            NodeProperties::of($node),
        );
    }

    /**
     * Returns one relation as a query sees it.
     *
     * A relation is identified by its two ends and its kind, which is exactly what
     * makes two relations the same one in the analysed graph. That keeps a path mode
     * that forbids crossing an edge twice forbidding the right thing.
     *
     * @param Edge $edge The analysed relation
     *
     * @example A relation is identified by what it joins and how
     *     $meta = new \App\Analyzer\Graph\FileMeta('/project/src/Invoice.php', 12, 1);
     *     $caller = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('App\\Invoice', 'total'), true);
     *     $called = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('App\\Money', 'add'), true);
     *     $written = new \App\Analyzer\Graph\Edge\Usage\MethodCallEdge($caller, $called, $meta);
     *     \App\Gql\Element\GraphProjection::edge($written)->id // => 'App\\Invoice::total|method-call|App\\Money::add'
     *
     * @return EdgeDatum The relation
     */
    public static function edge(Edge $edge): EdgeDatum
    {
        $origin = $edge->from()->toString();
        $target = $edge->to()->toString();

        return new EdgeDatum(
            $origin.'|'.$edge->kind()->value.'|'.$target,
            EdgeLabels::of($edge->kind()),
            EdgeProperties::of($edge),
            $origin,
            $target,
        );
    }
}
