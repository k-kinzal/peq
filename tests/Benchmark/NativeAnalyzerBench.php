<?php

declare(strict_types=1);

namespace Tests\Benchmark;

use App\Analyzer\NativeAnalyzer\NativeAnalyzer;
use App\Analyzer\NativeAnalyzer\SourceIndex;
use App\Analyzer\PhpFileCollector;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;

/**
 * Measures a whole analysis run of the engine that reads sources directly.
 *
 * Read next to PhpStanAnalyzerBench, which measures the same tree through the
 * reference engine: the two numbers together are the claim that this one is faster,
 * and neither of them is that claim on its own.
 *
 * @internal
 */
final class NativeAnalyzerBench
{
    /**
     * The source tree the measurement analyses.
     */
    private string $sourcePath = '';

    /**
     * The files of that tree, for the measurements that start after they are chosen.
     *
     * @var list<string>
     */
    private array $files = [];

    /**
     * Names the source tree to analyse.
     */
    public function setUp(): void
    {
        $this->sourcePath = dirname(__DIR__, 2).'/src';
        $this->files = (new PhpFileCollector())->collect([$this->sourcePath]);
    }

    /**
     * Measures one analysis run from the outside.
     */
    #[BeforeMethods('setUp')]
    #[Revs(1)]
    #[Iterations(5)]
    public function benchAnalyze(): void
    {
        (new NativeAnalyzer())->analyze($this->sourcePath);
    }

    /**
     * Measures reading and indexing the sources, which every run pays for once.
     */
    #[BeforeMethods('setUp')]
    #[Revs(1)]
    #[Iterations(5)]
    public function benchIndex(): void
    {
        SourceIndex::of($this->files, dirname(__DIR__, 2));
    }
}
