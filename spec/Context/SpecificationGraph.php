<?php

declare(strict_types=1);

namespace Spec\Context;

use App\Analyzer\Graph\Edge\Declaration\ExtendsEdge;
use App\Analyzer\Graph\Edge\Declaration\MethodEdge;
use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Gql\Element\ElementGraph;
use App\Gql\Element\GraphProjection;

/**
 * The graph every scenario of the specification is executed against.
 *
 * ISO/IEC 39075 specifies a language over a property graph, and says nothing about what
 * is in one. A specification of the language therefore needs a graph of its own, small
 * enough that a reader can work out by hand what a scenario should answer and see that
 * the stated answer is the one the standard requires rather than the one peq happens to
 * give.
 *
 * Three methods call round in a ring, one of them twice, and two classes declare them.
 * That is the smallest shape in which the four path modes of clause 16 disagree, which
 * is the hardest thing the specification has to state.
 */
final class SpecificationGraph
{
    /**
     * Returns the graph, as the query engine sees it.
     *
     * @return ElementGraph The graph
     */
    public static function elements(): ElementGraph
    {
        return GraphProjection::of(self::analysed());
    }

    /**
     * Returns the graph, as the analyser produces one.
     *
     * @return Graph The graph
     */
    public static function analysed(): Graph
    {
        $ring = new ClassNode(ClassNodeId::of('Spec\Ring'), true, self::at('Ring.php', 3));
        $base = new ClassNode(ClassNodeId::of('Spec\Base'), true, self::at('Base.php', 3));
        $first = new MethodNode(MethodNodeId::of('Spec\Ring', 'first'), true, self::at('Ring.php', 5));
        $second = new MethodNode(MethodNodeId::of('Spec\Ring', 'second'), true, self::at('Ring.php', 10));
        $third = new MethodNode(MethodNodeId::of('Spec\Ring', 'third'), true, self::at('Ring.php', 15));

        $graph = new Graph();
        $graph->addNodes([$ring, $base, $first, $second, $third]);
        $graph->addEdge(new ExtendsEdge($ring, $base, self::at('Ring.php', 3)));
        $graph->addEdge(new MethodEdge($ring, $first, self::at('Ring.php', 5)));
        $graph->addEdge(new MethodEdge($ring, $second, self::at('Ring.php', 10)));
        $graph->addEdge(new MethodEdge($ring, $third, self::at('Ring.php', 15)));
        $graph->addEdge(new MethodCallEdge($first, $second, self::at('Ring.php', 6)));
        $graph->addEdge(new MethodCallEdge($second, $third, self::at('Ring.php', 11)));
        $graph->addEdge(new MethodCallEdge($third, $first, self::at('Ring.php', 16)));
        $graph->addEdge(new MethodCallEdge($third, $second, self::at('Ring.php', 17)));

        return $graph;
    }

    /**
     * Returns where in the specification's own sources something is written.
     *
     * @param string $file Which file
     * @param int    $line Which line of it
     *
     * @return FileMeta The place
     */
    public static function at(string $file, int $line): FileMeta
    {
        return new FileMeta('/spec/'.$file, $line, 1);
    }
}
