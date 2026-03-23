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
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PHPStan\Analyser\Analyser as PhpStanAnalyser;
use PHPStan\DependencyInjection\Container;

/**
 * Breaks down PhpStanAnalyzer::analyze() into individual steps.
 *
 * @internal
 */
final class AnalyzeStepsBench
{
    /** @var string[] */
    private array $files = [];

    private ?Container $container = null;

    private ?PhpStanAnalyser $analyser = null;

    /** @var array<string, array<string, mixed>> */
    private array $collectedData = [];

    /** @var list<Edge|Node> */
    private array $items = [];

    private ?Graph $nodeOnlyGraph = null;

    public function setUpFiles(): void
    {
        $srcPath = dirname(__DIR__, 2).'/src';
        $collector = new PhpFileCollector();
        $this->files = $collector->collect([$srcPath]);
    }

    public function setUpContainer(): void
    {
        $this->setUpFiles();
        $factory = new ContainerFactory();
        $this->container = $factory->create($this->files);

        /** @phpstan-ignore phpstanApi.classConstant */
        $this->analyser = $this->container->getByType(PhpStanAnalyser::class);
    }

    public function setUpAnalysisResult(): void
    {
        $this->setUpContainer();

        /** @phpstan-ignore phpstanApi.class */
        assert($this->analyser instanceof PhpStanAnalyser);

        /** @phpstan-ignore phpstanApi.method */
        $result = $this->analyser->analyse($this->files, null, null, false, $this->files);

        /** @phpstan-ignore phpstanApi.method */
        $this->collectedData = $result->getCollectedData();
    }

    public function setUpItems(): void
    {
        $this->setUpAnalysisResult();
        $this->items = [];
        foreach ($this->collectedData as $data) {
            $this->collectItems($data, DependencyCollector::class, $this->items);
            $this->collectItems($data, InClassMethodCollector::class, $this->items);
        }
    }

    public function setUpNodeOnlyGraph(): void
    {
        $this->setUpItems();
        $this->nodeOnlyGraph = new Graph();
        foreach ($this->items as $item) {
            if ($item instanceof Node) {
                try {
                    $this->nodeOnlyGraph->addNode($item);
                } catch (\InvalidArgumentException) {
                }
            }
        }
    }

    /**
     * Measures PHPStan's $analyser->analyse() call only.
     * Container is pre-created in setUp.
     */
    #[BeforeMethods('setUpContainer')]
    #[Revs(1)]
    #[Iterations(3)]
    #[Groups(['steps'])]
    public function benchPhpStanAnalyse(): void
    {
        /** @phpstan-ignore phpstanApi.class */
        assert($this->analyser instanceof PhpStanAnalyser);

        /** @phpstan-ignore phpstanApi.method */
        $this->analyser->analyse($this->files, null, null, false, $this->files);
    }

    /**
     * Measures extracting collected data from analysis result.
     */
    #[BeforeMethods('setUpAnalysisResult')]
    #[Revs(5)]
    #[Iterations(5)]
    #[Groups(['steps'])]
    public function benchCollectData(): void
    {
        $items = [];
        foreach ($this->collectedData as $data) {
            $this->collectItems($data, DependencyCollector::class, $items);
            $this->collectItems($data, InClassMethodCollector::class, $items);
        }
    }

    /**
     * Measures graph node insertion (Pass 1).
     */
    #[BeforeMethods('setUpItems')]
    #[Revs(5)]
    #[Iterations(5)]
    #[Groups(['steps'])]
    public function benchBuildNodes(): void
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
    }

    /**
     * Measures graph edge insertion (Pass 2), nodes pre-loaded.
     */
    #[BeforeMethods('setUpNodeOnlyGraph')]
    #[Revs(5)]
    #[Iterations(5)]
    #[Groups(['steps'])]
    public function benchBuildEdges(): void
    {
        assert($this->nodeOnlyGraph instanceof Graph);

        // Clone isn't available, so rebuild nodes first then measure edges
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
