<?php

declare(strict_types=1);

namespace Tests\Benchmark;

use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use Tests\Fixture\Analyzer\AnalysisSteps;

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
        $this->files = AnalysisSteps::ownFiles();
    }

    /**
     * Measures one container build.
     */
    #[BeforeMethods('setUp')]
    #[Revs(1)]
    #[Iterations(3)]
    public function benchCreate(): void
    {
        AnalysisSteps::container($this->files);
    }
}
