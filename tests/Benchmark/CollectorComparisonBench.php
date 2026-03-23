<?php

declare(strict_types=1);

namespace Tests\Benchmark;

use App\Analyzer\PhpStanAnalyzer\Collector\DependencyCollector;
use App\Analyzer\PhpStanAnalyzer\Collector\InClassMethodCollector;
use App\Analyzer\PhpStanAnalyzer\PhpFileCollector;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PHPStan\Analyser\Analyser as PhpStanAnalyser;
use PHPStan\DependencyInjection\Container;
use PHPStan\DependencyInjection\ContainerFactory as PhpStanContainerFactory;
use Symfony\Component\Yaml\Yaml;

/**
 * Compares PHPStan analysis cost with/without InClassMethodCollector.
 *
 * InClassMethodCollector re-parses every source file per method to recover
 * AST nodes stripped by PHPStan v2's CleaningVisitor. This benchmark
 * isolates its cost by running analysis with DependencyCollector only
 * vs both collectors.
 *
 * @internal
 */
final class CollectorComparisonBench
{
    /** @var string[] */
    private array $files = [];

    private ?PhpStanAnalyser $analyserBothCollectors = null;

    private ?PhpStanAnalyser $analyserDependencyOnly = null;

    public function setUpBothCollectors(): void
    {
        $this->files = $this->collectFiles();
        $container = $this->createContainer([
            ['class' => DependencyCollector::class, 'tags' => ['phpstan.collector']],
            ['class' => InClassMethodCollector::class, 'tags' => ['phpstan.collector']],
        ]);

        /** @phpstan-ignore phpstanApi.classConstant */
        $this->analyserBothCollectors = $container->getByType(PhpStanAnalyser::class);
    }

    public function setUpDependencyOnly(): void
    {
        $this->files = $this->collectFiles();
        $container = $this->createContainer([
            ['class' => DependencyCollector::class, 'tags' => ['phpstan.collector']],
        ]);

        /** @phpstan-ignore phpstanApi.classConstant */
        $this->analyserDependencyOnly = $container->getByType(PhpStanAnalyser::class);
    }

    /**
     * Full analysis with both collectors (baseline).
     */
    #[BeforeMethods('setUpBothCollectors')]
    #[Revs(1)]
    #[Iterations(3)]
    #[Groups(['collectors'])]
    public function benchAnalyseWithBothCollectors(): void
    {
        /** @phpstan-ignore phpstanApi.class */
        assert($this->analyserBothCollectors instanceof PhpStanAnalyser);

        /** @phpstan-ignore phpstanApi.method */
        $this->analyserBothCollectors->analyse($this->files, null, null, false, $this->files);
    }

    /**
     * Analysis with DependencyCollector only (no file re-parsing).
     */
    #[BeforeMethods('setUpDependencyOnly')]
    #[Revs(1)]
    #[Iterations(3)]
    #[Groups(['collectors'])]
    public function benchAnalyseWithDependencyOnly(): void
    {
        /** @phpstan-ignore phpstanApi.class */
        assert($this->analyserDependencyOnly instanceof PhpStanAnalyser);

        /** @phpstan-ignore phpstanApi.method */
        $this->analyserDependencyOnly->analyse($this->files, null, null, false, $this->files);
    }

    /**
     * @return string[]
     */
    private function collectFiles(): array
    {
        $srcPath = dirname(__DIR__, 2).'/src';
        $collector = new PhpFileCollector();

        return $collector->collect([$srcPath]);
    }

    /**
     * @param list<array{class: class-string, tags: list<string>}> $services
     */
    private function createContainer(array $services): Container
    {
        $cwd = getcwd();
        if ($cwd === false) {
            throw new \RuntimeException('Unable to determine current working directory');
        }
        $containerFactory = new PhpStanContainerFactory($cwd);

        $tempDir = sys_get_temp_dir().'/peq-bench-'.uniqid();
        mkdir($tempDir);
        $tempConfig = $tempDir.'/phpstan.neon';

        $content = [
            'services' => $services,
            'parameters' => [
                'customRulesetUsed' => true,
                'level' => 5,
                'tmpDir' => $tempDir.'/tmp',
            ],
            'includes' => [],
        ];

        file_put_contents($tempConfig, Yaml::dump($content, 4));

        try {
            return $containerFactory->create($tempDir, [$tempConfig], $this->files);
        } finally {
            $this->deleteDirectory($tempDir);
        }
    }

    private function deleteDirectory(string $dir): void
    {
        if (!file_exists($dir)) {
            return;
        }
        $files = scandir($dir);
        if ($files === false) {
            return;
        }
        $files = array_diff($files, ['.', '..']);
        foreach ($files as $file) {
            (is_dir("{$dir}/{$file}")) ? $this->deleteDirectory("{$dir}/{$file}") : unlink("{$dir}/{$file}");
        }
        rmdir($dir);
    }
}
