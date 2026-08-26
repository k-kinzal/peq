<?php

declare(strict_types=1);

namespace Tests\Benchmark;

use App\Analyzer\AnalysisFailedException;
use App\Analyzer\PhpStanAnalyzer\CollectorReport;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PHPStan\DependencyInjection\Container;
use Tests\Fixture\Analyzer\AnalysisSteps;

/**
 * Breaks an analysis run into its steps and measures each of them.
 *
 * Analysing a source tree is one call from outside, and when it is slow the useful
 * question is which of its four steps the time went into: selecting the files,
 * building the analysis container, running the analysis, or assembling the graph.
 *
 * @internal
 */
final class AnalyzeStepsBench
{
    /**
     * @var list<string> The files of the analysed tree
     */
    private array $files = [];

    /**
     * The container the analysis runs in, once built.
     */
    private ?Container $container = null;

    /**
     * What the collectors reported, once the analysis has run.
     */
    private ?CollectorReport $report = null;

    /**
     * Selects the files every later step works on.
     */
    public function setUpFiles(): void
    {
        $this->files = AnalysisSteps::ownFiles();
    }

    /**
     * Builds the container the analysis runs in.
     */
    public function setUpContainer(): void
    {
        $this->setUpFiles();
        $this->container = AnalysisSteps::container($this->files);
    }

    /**
     * Runs the analysis so that only graph assembly is left to measure.
     *
     * @throws AnalysisFailedException If the container holds no analyser
     */
    public function setUpCollectedData(): void
    {
        $this->setUpContainer();
        $this->report = $this->container === null
            ? new CollectorReport([])
            : AnalysisSteps::collect($this->container, $this->files);
    }

    /**
     * Measures selecting the files an analysis covers.
     */
    #[Revs(5)]
    #[Iterations(5)]
    #[Groups(['steps'])]
    public function benchCollectFiles(): void
    {
        AnalysisSteps::ownFiles();
    }

    /**
     * Measures building the container the analysis runs in.
     */
    #[BeforeMethods('setUpFiles')]
    #[Revs(1)]
    #[Iterations(3)]
    #[Groups(['steps'])]
    public function benchBuildContainer(): void
    {
        AnalysisSteps::container($this->files);
    }

    /**
     * Measures the analysis itself, with the container already built.
     *
     * @throws AnalysisFailedException If the container holds no analyser
     */
    #[BeforeMethods('setUpContainer')]
    #[Revs(1)]
    #[Iterations(3)]
    #[Groups(['steps'])]
    public function benchAnalyse(): void
    {
        if ($this->container !== null) {
            AnalysisSteps::collect($this->container, $this->files);
        }
    }

    /**
     * Measures assembling the graph from what the collectors reported.
     */
    #[BeforeMethods('setUpCollectedData')]
    #[Revs(5)]
    #[Iterations(5)]
    #[Groups(['steps'])]
    public function benchBuildGraph(): void
    {
        AnalysisSteps::graph($this->report ?? new CollectorReport([]));
    }
}
