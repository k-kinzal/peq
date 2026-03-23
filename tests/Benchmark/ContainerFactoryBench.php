<?php

declare(strict_types=1);

namespace Tests\Benchmark;

use App\Analyzer\PhpStanAnalyzer\ContainerFactory;
use App\Analyzer\PhpStanAnalyzer\PhpFileCollector;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;

/**
 * @internal
 */
final class ContainerFactoryBench
{
    /** @var string[] */
    private array $files = [];

    public function setUp(): void
    {
        $srcPath = dirname(__DIR__, 2).'/src';
        $collector = new PhpFileCollector();
        $this->files = $collector->collect([$srcPath]);
    }

    #[BeforeMethods('setUp')]
    #[Revs(1)]
    #[Iterations(3)]
    public function benchCreate(): void
    {
        $factory = new ContainerFactory();
        $factory->create($this->files);
    }
}
