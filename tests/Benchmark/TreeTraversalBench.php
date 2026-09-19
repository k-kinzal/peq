<?php

declare(strict_types=1);

namespace Tests\Benchmark;

use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\Graph;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use App\Reporter\Traversal\DepthFirstTraversal;
use App\Reporter\TreeReporter\TreeReporter;
use App\Reporter\TreeReporter\TreeReporterOptions;
use Generator;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Measures reporting the tree of a symbol with many transitive relations.
 *
 * The cost of a report grows with how much of the graph it reaches, not with how big
 * the graph is, so the interesting numbers come from a symbol whose branches fan out
 * and from raising the level bound on it.
 *
 * @internal
 */
final class TreeTraversalBench
{
    /**
     * The graph of peq's own sources, once analysed.
     */
    private ?Graph $graph = null;

    /**
     * Analyses peq's own sources once, so that only reporting is measured.
     */
    public function setUp(): void
    {
        $this->graph = (new PhpStanAnalyzer())
            ->analyze(dirname(__DIR__, 2).'/src')
        ;
    }

    /**
     * Names the symbols and level bounds worth measuring.
     *
     * @return Generator<string, array{target: string, level: null|int}> One case per measurement
     */
    public function provideTraversalParams(): Generator
    {
        yield 'Graph L=3' => ['target' => 'App\Analyzer\Graph\Graph', 'level' => 3];

        yield 'Graph L=5' => ['target' => 'App\Analyzer\Graph\Graph', 'level' => 5];

        yield 'Graph unbounded' => ['target' => 'App\Analyzer\Graph\Graph', 'level' => null];

        yield 'InspectCommand L=3' => ['target' => 'App\Command\InspectCommand', 'level' => 3];

        yield 'InspectCommand L=5' => ['target' => 'App\Command\InspectCommand', 'level' => 5];
    }

    /**
     * Measures reporting what a symbol depends on.
     *
     * @param array{target: string, level: null|int} $params The symbol and level bound to measure
     */
    #[BeforeMethods('setUp')]
    #[ParamProviders('provideTraversalParams')]
    #[Revs(1)]
    #[Iterations(3)]
    public function benchReportDependencies(array $params): void
    {
        $this->report($params, Direction::Uses);
    }

    /**
     * Measures reporting what depends on a symbol.
     *
     * @param array{target: string, level: null|int} $params The symbol and level bound to measure
     */
    #[BeforeMethods('setUp')]
    #[ParamProviders('provideTraversalParams')]
    #[Revs(1)]
    #[Iterations(3)]
    public function benchReportDependents(array $params): void
    {
        $this->report($params, Direction::UsedBy);
    }

    /**
     * Writes one report to nowhere, so that only the work is measured.
     *
     * @param array{target: string, level: null|int} $params    The symbol and level bound to measure
     * @param Direction                              $direction Which way the graph is read
     */
    public function report(array $params, Direction $direction): void
    {
        $symbol = $this->graph?->nodeNamed($params['target']);
        if ($this->graph === null || $symbol === null) {
            return;
        }

        (new TreeReporter(new TreeReporterOptions(level: $params['level']), new DepthFirstTraversal($direction)))
            ->report($this->graph, $symbol->id(), new NullOutput())
        ;
    }
}
