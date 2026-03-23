<?php

declare(strict_types=1);

namespace Tests\Benchmark;

use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId;
use App\Analyzer\PhpStanAnalyzer\ContainerFactory;
use App\Analyzer\PhpStanAnalyzer\PhpFileCollector;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use App\Reporter\Traversal\DependencyTraversal;
use App\Reporter\Traversal\DependsTraversal;
use App\Reporter\TreeReporter\TreeReporter;
use App\Reporter\TreeReporter\TreeReporterOptions;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Benchmarks tree traversal with realistic targets.
 *
 * Uses a heavy node (InspectCommand, many transitive dependencies)
 * to expose the combinatorial explosion in the traversal.
 *
 * @internal
 */
final class TreeTraversalBench
{
    private ?Graph $graph = null;

    /** @var array<string, NodeId<Node>> */
    private array $symbols = [];

    public function setUp(): void
    {
        $srcPath = dirname(__DIR__, 2).'/src';
        $analyzer = new PhpStanAnalyzer(
            new ContainerFactory(),
            new PhpFileCollector(),
        );
        $this->graph = $analyzer->analyze($srcPath);

        $targets = [
            'App\Command\InspectCommand',
            'App\Analyzer\Graph\Graph',
        ];
        foreach ($this->graph->nodes() as $node) {
            if (in_array($node->id()->toString(), $targets, true)) {
                $this->symbols[$node->id()->toString()] = $node->id();
            }
        }
    }

    /**
     * @return \Generator<string, array{target: string, level: ?int}>
     */
    public function provideTraversalParams(): \Generator
    {
        yield 'Graph L=3' => ['target' => 'App\Analyzer\Graph\Graph', 'level' => 3];

        yield 'Graph L=5' => ['target' => 'App\Analyzer\Graph\Graph', 'level' => 5];

        yield 'Graph L=∞' => ['target' => 'App\Analyzer\Graph\Graph', 'level' => null];

        yield 'InspectCommand L=3' => ['target' => 'App\Command\InspectCommand', 'level' => 3];

        yield 'InspectCommand L=5' => ['target' => 'App\Command\InspectCommand', 'level' => 5];
    }

    /**
     * @param array{target: string, level: ?int} $params
     */
    #[BeforeMethods('setUp')]
    #[ParamProviders('provideTraversalParams')]
    #[Revs(1)]
    #[Iterations(3)]
    public function benchTraverseDependencies(array $params): void
    {
        assert($this->graph instanceof Graph);
        $symbol = $this->symbols[$params['target']] ?? null;
        assert($symbol instanceof NodeId);

        $reporter = new TreeReporter(
            options: new TreeReporterOptions(level: $params['level']),
            traversal: new DependencyTraversal(),
        );
        $reporter->report($this->graph, $symbol, new NullOutput());
    }

    /**
     * @param array{target: string, level: ?int} $params
     */
    #[BeforeMethods('setUp')]
    #[ParamProviders('provideTraversalParams')]
    #[Revs(1)]
    #[Iterations(3)]
    public function benchTraverseDependsOn(array $params): void
    {
        assert($this->graph instanceof Graph);
        $symbol = $this->symbols[$params['target']] ?? null;
        assert($symbol instanceof NodeId);

        $reporter = new TreeReporter(
            options: new TreeReporterOptions(level: $params['level']),
            traversal: new DependsTraversal(),
        );
        $reporter->report($this->graph, $symbol, new NullOutput());
    }
}
