<?php

declare(strict_types=1);

namespace App\Analyzer\DebugAnalyzer;

use App\Analyzer\Analyzer;
use App\Analyzer\DebugAnalyzer\Provider\AtomicProvider;
use App\Analyzer\DebugAnalyzer\Provider\ComponentProvider;
use App\Analyzer\DebugAnalyzer\Provider\GraphGenerator;
use App\Analyzer\DebugAnalyzer\Provider\GraphProvider;
use App\Analyzer\DebugAnalyzer\Provider\PrimitivesProvider;
use App\Analyzer\Graph\Graph;
use App\Config\Config;
use Faker;

/**
 * Debug analyzer that generates fake dependency graphs for testing and debugging purposes.
 *
 * This analyzer uses the Faker library with custom providers to generate realistic-looking
 * but fake PHP code structure graphs. It's useful for testing the graph visualization and
 * analysis features without needing real PHP code to parse.
 */
final class DebugAnalyzer implements Analyzer
{
    /**
     * @param null|int $seed  Random seed for reproducible fake data generation
     * @param int      $depth Maximum depth of the generated graph
     */
    public function __construct(
        private readonly ?int $seed = null,
        private readonly int $depth = 5,
    ) {}

    /**
     * Generates a fake dependency graph using Faker providers.
     *
     * This method creates a Faker generator instance with custom providers
     * that can generate graph structures with nodes and edges representing
     * PHP code elements and their relationships.
     *
     * @param string $path Ignored in this implementation (fake data doesn't need a real path)
     *
     * @return Graph A randomly generated dependency graph
     */
    public function analyze(string $path): Graph
    {
        /** @var GraphGenerator $faker */
        $faker = Faker\Factory::create();
        if ($this->seed !== null) {
            $faker->seed($this->seed);
        }
        $faker->addProvider(new PrimitivesProvider($faker));
        $faker->addProvider(new AtomicProvider($faker));
        $faker->addProvider(new ComponentProvider($faker));
        $faker->addProvider(new GraphProvider($faker));

        return $faker->graph($this->depth);
    }

    /**
     * Creates a DebugAnalyzer instance from the given configuration.
     *
     * @param Config $config The application configuration
     *
     * @return Analyzer A configured DebugAnalyzer instance
     *
     * @throws \InvalidArgumentException If the debug configuration is missing
     */
    public static function create(Config $config): Analyzer
    {
        if ($config->debug === null) {
            throw new \InvalidArgumentException('Debug configuration is required for DebugAnalyzer.');
        }

        return new self(
            seed: $config->debug->seed,
            depth: $config->debug->depth,
        );
    }
}
