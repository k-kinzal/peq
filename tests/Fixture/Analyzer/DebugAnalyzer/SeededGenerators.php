<?php

declare(strict_types=1);

namespace Tests\Fixture\Analyzer\DebugAnalyzer;

use App\Analyzer\DebugAnalyzer\Generator\ClassLikeGraphGenerator;
use App\Analyzer\DebugAnalyzer\Generator\FakerRandomSource;
use App\Analyzer\DebugAnalyzer\Generator\GeneratedGraph;
use App\Analyzer\DebugAnalyzer\Generator\GraphGenerator;
use App\Analyzer\DebugAnalyzer\Generator\LeafGraphGenerator;
use App\Analyzer\DebugAnalyzer\Generator\MemberGraphGenerator;
use App\Analyzer\DebugAnalyzer\Generator\NameGenerator;
use App\Analyzer\DebugAnalyzer\Generator\NodeGenerator;
use App\Analyzer\DebugAnalyzer\Generator\NodeIdGenerator;
use App\Analyzer\DebugAnalyzer\Generator\RandomSource;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Node;
use Faker\Factory;
use InvalidArgumentException;

/**
 * The generator stack, wired up over a seeded random source.
 *
 * Every generator is told where its randomness comes from, which is what makes a
 * generated graph reproducible. Seeding it here means a test states a seed and then
 * asserts on the result, rather than restating the wiring each time.
 */
final class SeededGenerators
{
    /**
     * Returns the random source peq draws from, seeded.
     *
     * @param int $seed The seed making the draws reproducible
     *
     * @return RandomSource The random source
     */
    public static function random(int $seed = 42): RandomSource
    {
        $faker = Factory::create();
        $faker->seed($seed);

        return new FakerRandomSource($faker);
    }

    /**
     * Returns a seeded generator of names.
     *
     * @param int $seed The seed making the draws reproducible
     *
     * @return NameGenerator The name generator
     */
    public static function names(int $seed = 42): NameGenerator
    {
        return new NameGenerator(self::random($seed));
    }

    /**
     * Returns a seeded generator of identifiers and source locations.
     *
     * @param int $seed The seed making the draws reproducible
     *
     * @return NodeIdGenerator The identifier generator
     */
    public static function ids(int $seed = 42): NodeIdGenerator
    {
        $random = self::random($seed);

        return new NodeIdGenerator(new NameGenerator($random), $random);
    }

    /**
     * Returns a seeded generator of nodes.
     *
     * @param int $seed The seed making the draws reproducible
     *
     * @return NodeGenerator The node generator
     */
    public static function nodes(int $seed = 42): NodeGenerator
    {
        $random = self::random($seed);

        return new NodeGenerator(new NodeIdGenerator(new NameGenerator($random), $random), $random);
    }

    /**
     * Returns a seeded generator of whole graphs, with its collaborators wired up.
     *
     * @param int $seed The seed making the draws reproducible
     *
     * @return GraphGenerator The graph generator
     */
    public static function graphs(int $seed = 42): GraphGenerator
    {
        return self::graphsDrawingFrom(self::random($seed));
    }

    /**
     * Returns a generator of whole graphs drawing from a sequence every PHP runtime shares.
     *
     * The random source peq draws from replays a seed only on the side of PHP 8.3 it
     * was seeded on. A graph written down to hold the generators to must not depend
     * on which side a run lands on, so this wiring draws from PortableDraws instead.
     *
     * @param int $seed The seed of the portable sequence
     *
     * @return GraphGenerator The graph generator
     */
    public static function portableGraphs(int $seed = 42): GraphGenerator
    {
        return self::graphsDrawingFrom(new PortableDraws($seed));
    }

    /**
     * Wires the generator stack over one random source.
     *
     * @param RandomSource $random The random source every generator draws from
     *
     * @return GraphGenerator The graph generator
     */
    public static function graphsDrawingFrom(RandomSource $random): GraphGenerator
    {
        $ids = new NodeIdGenerator(new NameGenerator($random), $random);
        $nodes = new NodeGenerator($ids, $random);

        return new GraphGenerator(
            classLikes: new ClassLikeGraphGenerator($nodes, $ids, $random),
            members: new MemberGraphGenerator($nodes, $ids, $random),
            leaves: new LeafGraphGenerator($nodes),
            ids: $ids,
            random: $random,
        );
    }

    /**
     * Generates a small graph through one named operation of the recursion contract.
     *
     * @param string $operation The name of the operation on the recursion contract
     * @param int    $depth     How many further levels of symbols to generate
     * @param int    $seed      The seed making the draws reproducible
     *
     * @return GeneratedGraph<Node> What that operation generated
     *
     * @throws InvalidArgumentException If the contract has no operation of that name
     */
    public static function symbolGraph(string $operation, int $depth = 2, int $seed = 42): GeneratedGraph
    {
        return self::symbolGraphOf(self::graphs($seed), $operation, $depth);
    }

    /**
     * Generates a small graph through one named operation of a given generator.
     *
     * The operations differ in arity — the three that relate to nothing further take
     * no depth — so a test that wants to exercise all of them uniformly asks here.
     * Naming them one by one is what makes an operation added to the contract, or
     * renamed in it, fail to compile here rather than fail at run time.
     *
     * @param GraphGenerator $generator The generator to ask
     * @param string         $operation The name of the operation on the recursion contract
     * @param int            $depth     How many further levels of symbols to generate
     *
     * @return GeneratedGraph<Node> What that operation generated
     *
     * @throws InvalidArgumentException If the contract has no operation of that name
     */
    public static function symbolGraphOf(GraphGenerator $generator, string $operation, int $depth): GeneratedGraph
    {
        return match ($operation) {
            'classGraph' => $generator->classGraph(null, $depth),
            'interfaceGraph' => $generator->interfaceGraph(null, $depth),
            'traitGraph' => $generator->traitGraph(null, $depth),
            'enumGraph' => $generator->enumGraph(null, $depth),
            'methodGraph' => $generator->methodGraph(null, $depth),
            'functionGraph' => $generator->functionGraph(null, $depth),
            'propertyGraph' => $generator->propertyGraph(null, $depth),
            'typeGraph' => $generator->typeGraph(null, $depth),
            'constantGraph' => $generator->constantGraph(),
            'enumCaseGraph' => $generator->enumCaseGraph(),
            'builtinGraph' => $generator->builtinGraph(),
            default => throw new InvalidArgumentException(sprintf('The recursion contract has no operation named "%s".', $operation)),
        };
    }

    /**
     * Spells every authored relation of a generated graph, in a stable order.
     *
     * @param GeneratedGraph<Node> $generated The generated graph
     *
     * @return list<string> One "from -[kind]-> to" line per relation, sorted
     */
    public static function relationsOf(GeneratedGraph $generated): array
    {
        $written = array_map(
            static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(),
            $generated->graph->authoredEdges(),
        );
        sort($written);

        return $written;
    }

    /**
     * Returns a seeded generator of the symbols that relate to nothing further.
     *
     * @param int $seed The seed making the draws reproducible
     *
     * @return LeafGraphGenerator The leaf generator
     */
    public static function leaves(int $seed = 42): LeafGraphGenerator
    {
        return new LeafGraphGenerator(self::nodes($seed));
    }

    /**
     * Returns a seeded generator of class-like symbols.
     *
     * @param int $seed The seed making the draws reproducible
     *
     * @return ClassLikeGraphGenerator The class-like generator
     */
    public static function classLikes(int $seed = 42): ClassLikeGraphGenerator
    {
        $random = self::random($seed);
        $ids = new NodeIdGenerator(new NameGenerator($random), $random);

        return new ClassLikeGraphGenerator(new NodeGenerator($ids, $random), $ids, $random);
    }

    /**
     * Returns a seeded generator of methods, functions and properties.
     *
     * @param int $seed The seed making the draws reproducible
     *
     * @return MemberGraphGenerator The member generator
     */
    public static function members(int $seed = 42): MemberGraphGenerator
    {
        $random = self::random($seed);
        $ids = new NodeIdGenerator(new NameGenerator($random), $random);

        return new MemberGraphGenerator(new NodeGenerator($ids, $random), $ids, $random);
    }
}
