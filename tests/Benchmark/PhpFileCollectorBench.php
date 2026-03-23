<?php

declare(strict_types=1);

namespace Tests\Benchmark;

use App\Analyzer\PhpStanAnalyzer\PhpFileCollector;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

/**
 * @internal
 */
final class PhpFileCollectorBench
{
    private string $srcPath = '';

    public function setUp(): void
    {
        $this->srcPath = dirname(__DIR__, 2).'/src';
    }

    #[BeforeMethods('setUp')]
    #[Revs(5)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchCollect(): void
    {
        $collector = new PhpFileCollector();
        $collector->collect([$this->srcPath]);
    }

    #[BeforeMethods('setUp')]
    #[Revs(5)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchCollectWithExcludes(): void
    {
        $collector = new PhpFileCollector();
        $collector->collect([$this->srcPath], [], ['*Test.php', '*Fixture*']);
    }
}
