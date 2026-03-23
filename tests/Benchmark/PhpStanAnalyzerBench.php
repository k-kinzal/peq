<?php

declare(strict_types=1);

namespace Tests\Benchmark;

use App\Analyzer\PhpStanAnalyzer\ContainerFactory;
use App\Analyzer\PhpStanAnalyzer\PhpFileCollector;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;

/**
 * @internal
 */
final class PhpStanAnalyzerBench
{
    private string $srcPath = '';

    public function setUp(): void
    {
        $this->srcPath = dirname(__DIR__, 2).'/src';
    }

    #[BeforeMethods('setUp')]
    #[Revs(1)]
    #[Iterations(3)]
    public function benchAnalyze(): void
    {
        $analyzer = new PhpStanAnalyzer(
            new ContainerFactory(),
            new PhpFileCollector(),
        );
        $analyzer->analyze($this->srcPath);
    }
}
