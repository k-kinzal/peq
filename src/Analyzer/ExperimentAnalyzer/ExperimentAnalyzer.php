<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer;

use App\Analyzer\Analyzer;
use App\Analyzer\Graph\Graph;
use App\Analyzer\PhpFileCollector;

/**
 * Experimental fork of the native analyzer, with a separate local data-flow graph.
 *
 * The native source pipeline was copied so experimental changes can evolve without
 * changing either production engine. analyze() retains its symbol graph contract;
 * inspect() answers the experimental question about a single callable's variables.
 */
final class ExperimentAnalyzer implements Analyzer
{
    /**
     * @param list<string>     $includes      File path patterns to include in analysis
     * @param list<string>     $excludes      File path patterns to exclude from analysis
     * @param null|int         $phpVersion    The PHP version the sources are read as, in PHP_VERSION_ID
     *                                        form, or null to read them as the version peq runs on
     * @param PhpFileCollector $fileCollector Selects which files the analysis covers
     */
    public function __construct(
        private readonly array $includes = [],
        private readonly array $excludes = [],
        private readonly ?int $phpVersion = null,
        private readonly PhpFileCollector $fileCollector = new PhpFileCollector(),
    ) {}

    /**
     * Builds a local variable graph for one written callable.
     *
     * @throws DataFlow\InspectionException If the target or its syntax cannot be inspected
     */
    public function inspect(string $path, string $target): DataFlow\DependencyGraph
    {
        $realPath = realpath($path);
        $files = $this->fileCollector->collect([$realPath !== false ? $realPath : $path], $this->includes, $this->excludes);
        $directory = getcwd();
        $index = SourceIndex::of($files, $directory === false ? '' : $directory, $this->phpVersion);

        $graph = (new DataFlow\Inspection())->inspect($index, $target);
        $graph->provenance += ['targetPhp' => $this->phpVersion ?? PHP_VERSION_ID, 'includes' => $this->includes, 'excludes' => $this->excludes];

        return $graph;
    }

    /**
     * Analyses the sources under the given path and builds their dependency graph.
     *
     * A path that holds no analysable file is not a failure: it produces an empty
     * graph, which is what the caller would otherwise have to construct itself.
     *
     * @param string $path The file or directory to analyse
     *
     * @return Graph The dependency graph of the analysed sources
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

        $workingDirectory = getcwd();
        $index = SourceIndex::of($files, $workingDirectory === false ? '' : $workingDirectory, $this->phpVersion);

        $recorder = new GraphRecorder();
        $walker = new SourceWalker($index, $recorder);
        foreach ($index->sources() as $source) {
            $walker->walkFile($source);
        }

        return $recorder->graph();
    }
}
