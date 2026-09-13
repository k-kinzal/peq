<?php

declare(strict_types=1);

namespace Tests\Fixture\Graph;

use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use Eris\Generator\SequenceGenerator;
use Eris\Generator\TupleGenerator;
use Eris\Generators;

/**
 * Relations drawn at random, and the graph they describe.
 *
 * A property of the graph model holds for any relations at all, so the values it is
 * checked over are drawn rather than chosen: a symbol name out of a small alphabet,
 * a kind out of the closed set, and as many relations as the property is given room
 * for. Drawing names from a small alphabet is deliberate — it is what makes a graph
 * of ten relations contain cycles, repeated relations and shared endpoints rather
 * than ten unrelated pairs.
 */
final class GeneratedRelations
{
    /**
     * The symbol names relations are drawn between.
     *
     * @var list<string>
     */
    private const NAMES = ['App\A', 'App\B', 'App\C', 'App\D'];

    /**
     * Draws a list of relations, each naming its two ends and its kind.
     *
     * @return SequenceGenerator<mixed> The generator, drawing a list of relations
     */
    public static function specifications(): SequenceGenerator
    {
        $authored = array_map(
            static fn (EdgeKind $kind): string => $kind->value,
            array_values(array_filter(
                EdgeKind::cases(),
                static fn (EdgeKind $kind): bool => $kind->direction() === Direction::Uses,
            )),
        );

        return new SequenceGenerator(new TupleGenerator([
            Generators::elements(self::NAMES),
            Generators::elements(self::NAMES),
            Generators::elements($authored),
        ]));
    }

    /**
     * Builds the graph a drawn list of relations describes.
     *
     * A drawn value arrives as whatever the generator produced, so each relation is
     * read rather than assumed: this is the boundary between a random source and the
     * graph, and the graph is the thing the property is about.
     *
     * @param array<mixed> $specifications The drawn relations
     *
     * @return Graph The graph holding them
     */
    public static function graphOf(array $specifications): Graph
    {
        $graph = new Graph();
        foreach ($specifications as $specification) {
            if (!is_array($specification) || count($specification) !== 3) {
                continue;
            }

            [$from, $to, $kind] = array_values($specification);
            if (!is_string($from) || !is_string($to) || !is_string($kind)) {
                continue;
            }

            $edgeKind = EdgeKind::tryFrom($kind);
            if ($edgeKind === null) {
                continue;
            }

            $graph->addEdge(new StubEdge(
                new StubNode(ClassNodeId::of($from)),
                new StubNode(ClassNodeId::of($to)),
                SampleEdges::meta(),
                $edgeKind,
            ));
        }

        return $graph;
    }
}
