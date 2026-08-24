<?php

declare(strict_types=1);

namespace Tests\Fixture\Analyzer;

use App\Analyzer\DebugAnalyzer\Generator\ClassLikeGraphGenerator;
use App\Analyzer\DebugAnalyzer\Generator\GeneratedGraph;
use App\Analyzer\DebugAnalyzer\Generator\GraphGenerator;
use App\Analyzer\DebugAnalyzer\Generator\LeafGraphGenerator;
use App\Analyzer\DebugAnalyzer\Generator\MemberGraphGenerator;
use App\Analyzer\DebugAnalyzer\Generator\NameGenerator;
use App\Analyzer\DebugAnalyzer\Generator\NodeGenerator;
use App\Analyzer\DebugAnalyzer\Generator\NodeIdGenerator;
use App\Analyzer\Graph\Node;
use Faker\Factory;
use Faker\Generator;

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
     * Returns a seeded random source.
     *
     * @param int $seed The seed making the draws reproducible
     *
     * @return Generator The random source
     */
    public static function faker(int $seed = 42): Generator
    {
        $faker = Factory::create();
        $faker->seed($seed);

        return $faker;
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
        return new NameGenerator(self::faker($seed));
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
        $faker = self::faker($seed);

        return new NodeIdGenerator(new NameGenerator($faker), $faker);
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
        $faker = self::faker($seed);

        return new NodeGenerator(new NodeIdGenerator(new NameGenerator($faker), $faker), $faker);
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
        $faker = self::faker($seed);
        $ids = new NodeIdGenerator(new NameGenerator($faker), $faker);
        $nodes = new NodeGenerator($ids, $faker);

        return new GraphGenerator(
            classLikes: new ClassLikeGraphGenerator($nodes, $ids, $faker),
            members: new MemberGraphGenerator($nodes, $ids, $faker),
            leaves: new LeafGraphGenerator($nodes),
            ids: $ids,
            faker: $faker,
        );
    }

    /**
     * Generates a small graph through one named operation of the recursion contract.
     *
     * The operations differ in arity — the three that relate to nothing further take
     * no depth — so a test that wants to exercise all of them uniformly asks here.
     *
     * @param string $operation The name of the operation on the recursion contract
     * @param int    $depth     How many further levels of symbols to generate
     * @param int    $seed      The seed making the draws reproducible
     *
     * @return GeneratedGraph<Node> What that operation generated
     */
    public static function symbolGraph(string $operation, int $depth = 2, int $seed = 42): GeneratedGraph
    {
        $generator = self::graphs($seed);

        return in_array($operation, ['constantGraph', 'enumCaseGraph', 'builtinGraph'], true)
            ? $generator->{$operation}(null)
            : $generator->{$operation}(null, $depth);
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
        $faker = self::faker($seed);
        $ids = new NodeIdGenerator(new NameGenerator($faker), $faker);

        return new ClassLikeGraphGenerator(new NodeGenerator($ids, $faker), $ids, $faker);
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
        $faker = self::faker($seed);
        $ids = new NodeIdGenerator(new NameGenerator($faker), $faker);

        return new MemberGraphGenerator(new NodeGenerator($ids, $faker), $ids, $faker);
    }
}
