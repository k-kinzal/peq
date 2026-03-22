<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer;

use App\Analyzer\Analyzer;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\PhpStanAnalyzer\Collector\DependencyCollector;
use App\Analyzer\PhpStanAnalyzer\Collector\InClassMethodCollector;
use PHPStan\Analyser\Analyser as PhpStanAnalyser;

final class PhpStanAnalyzer implements Analyzer
{
    public function __construct(
        private readonly ContainerFactory $containerFactory,
        private readonly PhpFileCollector $fileCollector,
    ) {}

    public function analyze(string $path): Graph
    {
        $realPath = realpath($path);
        $absolutePath = $realPath !== false ? $realPath : $path;
        $files = $this->fileCollector->collect([$absolutePath]);

        if ($files === []) {
            return new Graph();
        }

        $container = $this->containerFactory->create($files);
        // @phpstan-ignore phpstanApi.classConstant
        $analyser = $container->getByType(PhpStanAnalyser::class);

        // Run analysis
        // @phpstan-ignore phpstanApi.method
        $result = $analyser->analyse($files, null, null, false, $files);

        // Retrieve collected data
        // @phpstan-ignore phpstanApi.method
        $collectedData = $result->getCollectedData();

        // Filter for our collector
        /** @var array<Edge|Node> $items */
        $items = [];
        foreach ($collectedData as $file => $data) {
            $this->collectItems($data, DependencyCollector::class, $items);
            $this->collectItems($data, InClassMethodCollector::class, $items);
        }

        $graph = new Graph();

        // Pass 1: Add all Nodes
        foreach ($items as $item) {
            if ($item instanceof Node) {
                try {
                    $graph->addNode($item);
                } catch (\InvalidArgumentException) {
                    // Ignore duplicates
                }
            }
        }

        // Pass 2: Add all Edges
        foreach ($items as $item) {
            if ($item instanceof Edge) {
                $graph->addEdge($item);
            }
        }

        return $graph;
    }

    /**
     * @param array<string, mixed> $data
     * @param class-string         $collectorClass
     * @param array<Edge|Node>     $items
     */
    private function collectItems(array $data, string $collectorClass, array &$items): void
    {
        if (!isset($data[$collectorClass]) || !is_array($data[$collectorClass])) {
            return;
        }

        foreach ($data[$collectorClass] as $nodeItems) {
            if (is_array($nodeItems)) {
                /** @var array<Edge|Node> $nodeItems */
                foreach ($nodeItems as $item) {
                    $items[] = $item;
                }
            }
        }
    }
}
