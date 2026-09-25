<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer;

use App\Analyzer\AnalysisFailedException;
use App\Analyzer\AnalysisInputs;
use App\Analyzer\Analyzer;
use App\Analyzer\CallEnrichment;
use App\Analyzer\Declaration\PhpDoc\DocDependencies;
use App\Analyzer\Graph\Graph;
use App\Analyzer\PhaseCache;
use App\Analyzer\PhpFileCollector;
use App\Analyzer\PhpStanAnalyzer\Collector\DependencyCollector;
use App\Analyzer\PhpStanAnalyzer\Collector\InClassMethodCollector;
use App\Analyzer\SourceParser;
use Override;
use PHPStan\Analyser\Analyser as PhpStanAnalyser;
use PHPStan\Analyser\Error;
use PHPStan\DependencyInjection\Container;
use PHPStan\DependencyInjection\MissingServiceException;
use PHPStan\Parser\PathRoutingParser;

/**
 * Builds the dependency graph by having PHPStan analyse real sources.
 *
 * Resolving what a name refers to is the hard part of reading PHP, and PHPStan
 * already does it: this analyzer runs PHPStan over the selected files with peq's own
 * collectors registered, and assembles the nodes and edges they report. Using
 * PHPStan's internals is deliberate — its resolved types are the reason the graph
 * can tell a call to one class from a call to another of the same method name.
 */
final readonly class PhpStanAnalyzer implements Analyzer
{
    /**
     * The identifier PHPStan gives an error that stands for a failure of its own.
     *
     * Every other error is a diagnostic about the analysed code, which a dependency
     * graph has no use for — including the syntax error of a file no PHP version
     * would accept, which PHPStan reads past and so does peq.
     */
    private const string INTERNAL_ERROR = 'phpstan.internal';

    /**
     * @param list<string>       $includes         File path patterns to include in analysis
     * @param list<string>       $excludes         File path patterns to exclude from analysis
     * @param null|int           $phpVersion       The PHP version the sources are read as, in PHP_VERSION_ID
     *                                             form, or null to leave the version to the analysis engine
     * @param ContainerFactory   $containerFactory Builds the PHPStan container that runs the collectors
     * @param PhpFileCollector   $fileCollector    Selects which files the analysis covers
     * @param GraphBuilder       $graphBuilder     Assembles the graph from what the collectors reported
     * @param list<class-string> $collectors       The collectors the graph is assembled from: declarations and method bodies unless narrowed
     * @param null|PhaseCache    $cache            Reuses completed phases, or null for uncached analysis
     */
    public function __construct(
        private array $includes = [],
        private array $excludes = [],
        private ?int $phpVersion = null,
        private ContainerFactory $containerFactory = new ContainerFactory(),
        private PhpFileCollector $fileCollector = new PhpFileCollector(),
        private GraphBuilder $graphBuilder = new GraphBuilder(),
        private array $collectors = [DependencyCollector::class, InClassMethodCollector::class],
        private ?PhaseCache $cache = null,
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
     * @throws AnalysisFailedException If PHPStan cannot be configured to run the collectors,
     *                                 or if it could not finish analysing one of the files
     */
    #[Override]
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

        $build = function () use ($files): Graph {
            $report = $this->collect($this->containerFactory->create($files, $this->collectors, $this->phpVersion), $files);

            return DocDependencies::enrich($this->graphBuilder->build($report->symbols()), $files, SourceParser::forVersion($this->phpVersion));
        };
        if ($this->cache === null) {
            return CallEnrichment::of($build(), $this->phpVersion);
        }
        $slot = serialize([$realPath !== false ? $realPath : $path, getcwd(), $this->includes, $this->excludes, $this->phpVersion, $this->collectors]);
        $fingerprint = AnalysisInputs::fingerprint($files, [$this->phpVersion, $this->collectors]);

        return $this->cache->remember('phpstan-enriched', $slot, $fingerprint, Graph::class, fn (): Graph => CallEnrichment::of(
            $this->cache->remember('phpstan-graph', $slot, $fingerprint, Graph::class, $build),
            $this->phpVersion,
            $this->cache,
        ));
    }

    /**
     * Runs the analysis and reads back what peq's collectors reported.
     *
     * Running PHPStan in process is what gives peq the resolved types a dependency
     * graph needs, and this is the only place that does it: the container, the
     * analyser and the shape of its result are PHPStan's own vocabulary, and every
     * other part of peq works on the symbols this hands back.
     *
     * PHPStan parses a file in full only when its parser has been told the file is
     * one of those analysed; any other file is read with its function bodies
     * stripped, which is enough for reflection but leaves nothing for a collector
     * to see inside a function. PHPStan's own commands register the analysed files
     * with the parser before analysing, and so does this.
     *
     * A file PHPStan could not finish is reported as a failure rather than left out:
     * a graph that quietly lacks a file would claim that nothing depends on what
     * that file declares, which is the one answer an impact analysis must never get
     * wrong. PHPStan records such a failure as an internal error next to the
     * diagnostics peq discards, so those are read before the collected data is. The
     * message names the PHP version the sources were read as, because a file the
     * analysis could not get through is most often one written for another version.
     *
     * @param Container    $container The container the analysis runs in
     * @param list<string> $files     The files to analyse
     *
     * @return CollectorReport What the collectors reported
     *
     * @throws AnalysisFailedException If the container was built without a parser or an
     *                                 analyser, or if PHPStan could not finish analysing one of the files
     */
    public function collect(Container $container, array $files): CollectorReport
    {
        try {
            $parser = $container->getService('pathRoutingParser');
            $analyser = $container->getByType(PhpStanAnalyser::class);
        } catch (MissingServiceException $missing) {
            throw new AnalysisFailedException(
                'PHPStan was built without its parser or its analyser, so no source can be analysed.',
                0,
                $missing,
            );
        }
        if (!is_object($parser) || $parser::class !== PathRoutingParser::class) {
            throw new AnalysisFailedException('PHPStan has no parser to tell which files are analysed, so no source can be analysed.');
        }
        $parser->setAnalysedFiles($files);

        $result = $analyser->analyse($files, null, null, false, $files);
        $failures = array_values(array_filter(
            $result->getErrors(),
            static fn (Error $error): bool => $error->getIdentifier() === self::INTERNAL_ERROR,
        ));
        if ($failures !== []) {
            throw new AnalysisFailedException(implode(PHP_EOL, array_map(
                fn (Error $failure): string => sprintf(
                    'PHPStan could not finish analysing %s as %s: %s',
                    $failure->getFile(),
                    $this->analysedVersion(),
                    $failure->getMessage(),
                ),
                $failures,
            )));
        }

        return CollectorReport::of($result->getCollectedData(), $this->collectors);
    }

    /**
     * Names the PHP version the sources are read as.
     *
     * A file that could not be read is nearly always a file written for a different
     * version than the one it was read as, so the message about it says which version
     * that was — including when nobody chose it.
     *
     * @return string The version as a project would write it, or a name for the unstated one
     */
    public function analysedVersion(): string
    {
        if ($this->phpVersion === null) {
            return 'the PHP version peq runs on';
        }

        return sprintf('PHP %d.%d', intdiv($this->phpVersion, 10000), intdiv($this->phpVersion, 100) % 100);
    }
}
