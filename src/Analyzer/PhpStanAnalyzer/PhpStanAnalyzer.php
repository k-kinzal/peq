<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer;

use App\Analyzer\AnalysisFailedException;
use App\Analyzer\Analyzer;
use App\Analyzer\Graph\Graph;
use App\Analyzer\PhpStanAnalyzer\Collector\DependencyCollector;
use App\Analyzer\PhpStanAnalyzer\Collector\InClassMethodCollector;
use PHPStan\Analyser\Analyser as PhpStanAnalyser;
use PHPStan\DependencyInjection\Container;
use PHPStan\DependencyInjection\MissingServiceException;

/**
 * Builds the dependency graph by having PHPStan analyse real sources.
 *
 * Resolving what a name refers to is the hard part of reading PHP, and PHPStan
 * already does it: this analyzer runs PHPStan over the selected files with peq's own
 * collectors registered, and assembles the nodes and edges they report. Using
 * PHPStan's internals is deliberate — its resolved types are the reason the graph
 * can tell a call to one class from a call to another of the same method name.
 */
final class PhpStanAnalyzer implements Analyzer
{
    /**
     * @param list<string>     $includes         File path patterns to include in analysis
     * @param list<string>     $excludes         File path patterns to exclude from analysis
     * @param ContainerFactory $containerFactory Builds the PHPStan container that runs the collectors
     * @param PhpFileCollector $fileCollector    Selects which files the analysis covers
     * @param GraphBuilder     $graphBuilder     Assembles the graph from what the collectors reported
     */
    public function __construct(
        private readonly array $includes = [],
        private readonly array $excludes = [],
        private readonly ContainerFactory $containerFactory = new ContainerFactory(),
        private readonly PhpFileCollector $fileCollector = new PhpFileCollector(),
        private readonly GraphBuilder $graphBuilder = new GraphBuilder(),
    ) {}

    /**
     * Analyses the sources under the given path and builds their dependency graph.
     *
     * A path that holds no analysable file is not a failure: it produces an empty
     * graph, which is what the caller would otherwise have to construct itself.
     *
     * @param string $path The file or directory to analyse
     *
     * @return Graph The dependency graph of the analysed sources
     *
     * @throws AnalysisFailedException If PHPStan cannot be configured to run the collectors
     */
    public function analyze(string $path): Graph
    {
        $realPath = realpath($path);
        $files = $this->fileCollector->collect(
            [$realPath !== false ? $realPath : $path],
            $this->includes,
            $this->excludes,
        );

        if ($files === []) {
            return new Graph();
        }

        $report = $this->collect($this->containerFactory->create($files), $files);

        return $this->graphBuilder->build($report->symbols());
    }

    /**
     * Runs the analysis and reads back what peq's collectors reported.
     *
     * Running PHPStan in process is what gives peq the resolved types a dependency
     * graph needs, and this is the only place that does it: the container, the
     * analyser and the shape of its result are PHPStan's own vocabulary, and every
     * other part of peq works on the symbols this hands back.
     *
     * @param Container    $container The container the analysis runs in
     * @param list<string> $files     The files to analyse
     *
     * @return CollectorReport What the collectors reported
     *
     * @throws AnalysisFailedException If the container was built without an analyser
     */
    public function collect(Container $container, array $files): CollectorReport
    {
        try {
            $analyser = $container->getByType(PhpStanAnalyser::class);
        } catch (MissingServiceException $missing) {
            throw new AnalysisFailedException(
                'PHPStan was built without its analyser, so no source can be analysed.',
                0,
                $missing,
            );
        }

        return CollectorReport::of(
            $analyser->analyse($files, null, null, false, $files)->getCollectedData(),
            [DependencyCollector::class, InClassMethodCollector::class],
        );
    }
}
