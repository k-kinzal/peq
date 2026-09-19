<?php

declare(strict_types=1);

namespace Tests\Benchmark;

use App\Analyzer\AnalysisFailedException;
use App\Analyzer\PhpFileCollector;
use App\Analyzer\PhpStanAnalyzer\Collector\DependencyCollector;
use App\Analyzer\PhpStanAnalyzer\Collector\InClassMethodCollector;
use App\Analyzer\PhpStanAnalyzer\CollectorReport;
use App\Analyzer\PhpStanAnalyzer\ContainerFactory;
use App\Analyzer\PhpStanAnalyzer\GraphBuilder;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;

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
        $files = (new PhpFileCollector())->collect([dirname(__DIR__, 2).'/src']);
        $this->report = (new PhpStanAnalyzer())->collect((new ContainerFactory())->create($files, [DependencyCollector::class, InClassMethodCollector::class]), $files);
    }

    /**
     * Measures assembling the whole graph.
     */
    #[BeforeMethods('setUp')]
    #[Revs(5)]
    #[Iterations(5)]
    public function benchBuildGraph(): void
    {
        (new GraphBuilder())->build(($this->report ?? new CollectorReport([]))->symbols());
    }
}
