<?php

declare(strict_types=1);

namespace Tests\Benchmark;

use App\Analyzer\PhpStanAnalyzer\PhpFileCollector;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

/**
 * Measures selecting the files an analysis covers.
 *
 * Filtering costs a directory walk of its own, so the two measurements together say
 * what narrowing an analysis with patterns actually buys.
 *
 * @internal
 */
final class PhpFileCollectorBench
{
    /**
     * The source tree the measurements walk.
     */
    private string $sourcePath = '';

    /**
     * Names the source tree to walk.
     */
    public function setUp(): void
    {
        $this->sourcePath = dirname(__DIR__, 2).'/src';
    }

    /**
     * Measures selecting every PHP file of the tree.
     */
    #[BeforeMethods('setUp')]
    #[Revs(5)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchCollect(): void
    {
        (new PhpFileCollector())->collect([$this->sourcePath]);
    }

    /**
     * Measures selecting the files of the tree with exclude patterns applied.
     */
    #[BeforeMethods('setUp')]
    #[Revs(5)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchCollectWithExcludes(): void
    {
        (new PhpFileCollector())->collect([$this->sourcePath], [], ['Graph', 'Processor']);
    }
}
