<?php

declare(strict_types=1);

namespace Tests\Benchmark;

use App\Analyzer\PhpFileCollector;
use App\Analyzer\PhpStanAnalyzer\Collector\DependencyCollector;
use App\Analyzer\PhpStanAnalyzer\Collector\InClassMethodCollector;
use App\Analyzer\PhpStanAnalyzer\ContainerFactory;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;

/**
 * Measures building the container an analysis runs in.
 *
 * The container is compiled from a generated configuration file, which makes it the
 * fixed cost of every run however small the analysed tree is.
 *
 * @internal
 */
final class ContainerFactoryBench
{
    /**
     * @var list<string> The files of the analysed tree
     */
    private array $files = [];

    /**
     * Selects the files the container will be told about.
     */
    public function setUp(): void
    {
        $this->files = (new PhpFileCollector())->collect([dirname(__DIR__, 2).'/src']);
    }

    /**
     * Measures one container build.
     */
    #[BeforeMethods('setUp')]
    #[Revs(1)]
    #[Iterations(3)]
    public function benchCreate(): void
    {
        (new ContainerFactory())->create($this->files, [DependencyCollector::class, InClassMethodCollector::class]);
    }
}
