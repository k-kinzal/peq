<?php

declare(strict_types=1);

namespace App\Analyzer\NativeAnalyzer;

use App\Analyzer\Analyzer;
use App\Analyzer\CallEnrichment;
use App\Analyzer\Declaration\PhpDoc\DocDependencies;
use App\Analyzer\Graph\Graph;
use App\Analyzer\PhpFileCollector;
use App\Analyzer\SourceParser;

/**
 * Builds the dependency graph by reading the sources directly.
 *
 * This is the same graph PhpStanAnalyzer builds, built without PHPStan. Running a
 * static analyser to get a dependency graph is paying for a great deal that a graph
 * never asks for — inferring the type of every expression, reflecting over every
 * class of the project, compiling a container to do it in — and the relations peq
 * records turn out not to need any of it: every one of them names its target in the
 * source, and the few names whose meaning depends on where they are written are
 * settled by what the analysed files themselves declare.
 *
 * What it does need, and what PhpStanAnalyzer gets from PHPStan for free, is PHP's
 * own resolution rules: the methods a class takes on from the traits it uses, the
 * namespace fallback of an unqualified function call, and the names analysis invents
 * for anonymous classes. Those are read out of the analysed files before any of them
 * is walked, which is why the analysis is two passes and not one.
 *
 * Producing the same graph is not a claim this class makes about itself: both
 * analyzers are checked against each other over a corpus built to exercise every
 * kind of symbol and relation the graph model has, and the check is what the claim
 * rests on.
 */
final class NativeAnalyzer implements Analyzer
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

        $graph = DocDependencies::enrich($recorder->graph(), $files, SourceParser::forVersion($this->phpVersion));

        return CallEnrichment::of($graph, $this->phpVersion);
    }
}
