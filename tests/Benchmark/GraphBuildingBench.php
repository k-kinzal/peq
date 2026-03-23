<?php

declare(strict_types=1);

namespace Tests\Benchmark;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\PhpStanAnalyzer\Collector\DependencyCollector;
use App\Analyzer\PhpStanAnalyzer\Collector\InClassMethodCollector;
use App\Analyzer\PhpStanAnalyzer\ContainerFactory;
use App\Analyzer\PhpStanAnalyzer\PhpFileCollector;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PHPStan\Analyser\Analyser as PhpStanAnalyser;

/**
 * @internal
 */
final class GraphBuildingBench
{
    /** @var list<Edge|Node> */
    private array $items = [];

    public function setUp(): void
    {
        $srcPath = dirname(__DIR__, 2).'/src';
        $collector = new PhpFileCollector();
        $files = $collector->collect([$srcPath]);

        $factory = new ContainerFactory();
        $container = $factory->create($files);

        /** @phpstan-ignore phpstanApi.classConstant */
        $analyser = $container->getByType(PhpStanAnalyser::class);

        /** @phpstan-ignore phpstanApi.method */
        $result = $analyser->analyse($files, null, null, false, $files);

        /** @phpstan-ignore phpstanApi.method */
        $collectedData = $result->getCollectedData();

        $this->items = [];
        foreach ($collectedData as $data) {
            $this->collectItems($data, DependencyCollector::class, $this->items);
            $this->collectItems($data, InClassMethodCollector::class, $this->items);
        }
    }

    #[BeforeMethods('setUp')]
    #[Revs(5)]
    #[Iterations(5)]
    public function benchBuildGraph(): void
    {
        $graph = new Graph();

        foreach ($this->items as $item) {
            if ($item instanceof Node) {
                try {
                    $graph->addNode($item);
                } catch (\InvalidArgumentException) {
                }
            }
        }

        foreach ($this->items as $item) {
            if ($item instanceof Edge) {
                $graph->addEdge($item);
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param class-string         $collectorClass
     * @param list<Edge|Node>      $items
     */
    private function collectItems(array $data, string $collectorClass, array &$items): void
    {
        if (!isset($data[$collectorClass]) || !is_array($data[$collectorClass])) {
            return;
        }

        foreach ($data[$collectorClass] as $nodeItems) {
            if (is_array($nodeItems)) {
                /** @var list<Edge|Node> $nodeItems */
                foreach ($nodeItems as $item) {
                    $items[] = $item;
                }
            }
        }
    }
}
