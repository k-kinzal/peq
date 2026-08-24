<?php

declare(strict_types=1);

namespace Tests\Fixture\Analyzer;

use App\Analyzer\Graph\Graph;
use App\Analyzer\PhpStanAnalyzer\Collector\DependencyCollector;
use App\Analyzer\PhpStanAnalyzer\Collector\InClassMethodCollector;
use App\Analyzer\PhpStanAnalyzer\ContainerFactory;
use App\Analyzer\PhpStanAnalyzer\GraphBuilder;
use App\Analyzer\PhpStanAnalyzer\PhpFileCollector;
use PHPStan\Analyser\Analyser;
use PHPStan\DependencyInjection\Container;

/**
 * The steps peq's real analyzer runs, exposed one at a time.
 *
 * A benchmark that measures where the time goes has to be able to stop between
 * the steps: select the files, build the container, run the analysis, assemble the
 * graph. The analyzer runs them as one call, so the breakdown lives here rather
 * than being restated in every benchmark that wants a different step.
 */
final class AnalysisSteps
{
    /**
     * Returns the source files of peq itself.
     *
     * @return list<string> Absolute paths of everything under src/
     */
    public static function ownFiles(): array
    {
        return (new PhpFileCollector())->collect([dirname(__DIR__, 3).'/src']);
    }

    /**
     * Builds the PHPStan container that runs peq's collectors over those files.
     *
     * @param list<string> $files The files the analysis will cover
     *
     * @return Container The configured container
     */
    public static function container(array $files): Container
    {
        return (new ContainerFactory())->create($files);
    }

    /**
     * Runs the analysis and returns what the collectors reported.
     *
     * @param Container    $container The container to analyse with
     * @param list<string> $files     The files to analyse
     *
     * @return array<string, mixed> What the collectors reported, keyed by file
     *
     * @throws \PHPStan\DependencyInjection\MissingServiceException If the container holds no analyser
     */
    public static function collect(Container $container, array $files): array
    {
        return $container->getByType(Analyser::class)->analyse($files, null, null, false, $files)->getCollectedData();
    }

    /**
     * Assembles the graph from what the collectors reported.
     *
     * @param array<string, mixed> $collected    What the collectors reported
     * @param bool                 $methodBodies Whether the findings of method bodies are included
     *
     * @return Graph The graph those findings describe
     */
    public static function graph(array $collected, bool $methodBodies = true): Graph
    {
        $collectors = $methodBodies
            ? [DependencyCollector::class, InClassMethodCollector::class]
            : [DependencyCollector::class];

        return (new GraphBuilder())->build($collected, $collectors);
    }
}
