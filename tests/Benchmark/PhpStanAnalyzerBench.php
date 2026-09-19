<?php

declare(strict_types=1);

namespace Tests\Benchmark;

use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;

/**
 * Measures a whole analysis run, the way a user experiences it.
 *
 * This is the number that matters at the command line; the step breakdown says where
 * it comes from.
 *
 * @internal
 */
final class PhpStanAnalyzerBench
{
    /**
     * The source tree the measurement analyses.
     */
    private string $sourcePath = '';

    /**
     * Names the source tree to analyse.
     */
    public function setUp(): void
    {
        $this->sourcePath = dirname(__DIR__, 2).'/src';
    }

    /**
     * Measures one analysis run from the outside.
     */
    #[BeforeMethods('setUp')]
    #[Revs(1)]
    #[Iterations(3)]
    public function benchAnalyze(): void
    {
        (new PhpStanAnalyzer())->analyze($this->sourcePath);
    }
}
