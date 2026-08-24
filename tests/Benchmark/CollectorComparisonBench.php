<?php

declare(strict_types=1);

namespace Tests\Benchmark;

use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use Tests\Fixture\Analyzer\AnalysisSteps;

/**
 * Measures what reading method bodies costs.
 *
 * Declarations come straight out of the tree the analysis already built, while the
 * relations written inside method bodies need the sources to be read again. This
 * compares assembling a graph from declarations alone with assembling one from both,
 * which is what says whether that second read is worth its price.
 *
 * @internal
 */
final class CollectorComparisonBench
{
    /**
     * @var array<string, mixed> What the collectors reported for peq's own sources
     */
    private array $collected = [];

    /**
     * Runs the analysis once so that only assembly is measured.
     *
     * @throws \PHPStan\DependencyInjection\MissingServiceException If the container holds no analyser
     */
    public function setUp(): void
    {
        $files = AnalysisSteps::ownFiles();
        $this->collected = AnalysisSteps::collect(AnalysisSteps::container($files), $files);
    }

    /**
     * Measures assembling a graph from declarations alone.
     */
    #[BeforeMethods('setUp')]
    #[Revs(5)]
    #[Iterations(5)]
    public function benchDeclarationsOnly(): void
    {
        AnalysisSteps::graph($this->collected, methodBodies: false);
    }

    /**
     * Measures assembling a graph from declarations and method bodies together.
     */
    #[BeforeMethods('setUp')]
    #[Revs(5)]
    #[Iterations(5)]
    public function benchDeclarationsAndMethodBodies(): void
    {
        AnalysisSteps::graph($this->collected, methodBodies: true);
    }
}
