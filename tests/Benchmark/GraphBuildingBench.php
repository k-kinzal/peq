<?php

declare(strict_types=1);

namespace Tests\Benchmark;

use App\Analyzer\AnalysisFailedException;
use App\Analyzer\PhpStanAnalyzer\CollectorReport;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use Tests\Fixture\Analyzer\AnalysisSteps;

/**
 * Measures assembling a graph out of what an analysis reported.
 *
 * This is the step whose cost grows with the size of the codebase rather than with
 * the cost of understanding it, so it is the one to watch when the graph model
 * changes.
 *
 * @internal
 */
final class GraphBuildingBench
{
    /**
     * What the collectors reported for peq's own sources.
     */
    private ?CollectorReport $report = null;

    /**
     * Runs the analysis once so that only assembly is measured.
     *
     * @throws AnalysisFailedException If the container holds no analyser
     */
    public function setUp(): void
    {
        $files = AnalysisSteps::ownFiles();
        $this->report = AnalysisSteps::collect(AnalysisSteps::container($files), $files);
    }

    /**
     * Measures assembling the whole graph.
     */
    #[BeforeMethods('setUp')]
    #[Revs(5)]
    #[Iterations(5)]
    public function benchBuildGraph(): void
    {
        AnalysisSteps::graph($this->report ?? new CollectorReport([]));
    }
}
