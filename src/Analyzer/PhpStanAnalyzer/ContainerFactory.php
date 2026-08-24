<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer;

use App\Analyzer\PhpStanAnalyzer\Collector\DependencyCollector;
use App\Analyzer\PhpStanAnalyzer\Collector\InClassMethodCollector;
use PHPStan\DependencyInjection\Container;
use PHPStan\DependencyInjection\ContainerFactory as PhpStanContainerFactory;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * Builds a PHPStan container that runs peq's collectors.
 *
 * PHPStan's container is compiled from configuration files and cannot be extended
 * once built, so registering a collector means handing PHPStan a configuration file
 * that declares it. That file is generated for the run and thrown away afterwards,
 * which is why a scratch directory is involved: Nette's dependency injection derives
 * its own cache location from the configuration path, so the configuration cannot
 * simply live next to the analysed project.
 *
 * @visibility namespace
 */
final class ContainerFactory
{
    /**
     * Analysis level of the generated configuration.
     *
     * Collectors run at every level, and level 0 leaves out the type-checking rules
     * peq has no use for, so nothing is spent on diagnostics that are discarded.
     */
    private const COLLECTOR_ONLY_LEVEL = 0;

    /**
     * @param PhpStanAutoloader $autoloader Makes PHPStan loadable when peq runs from a PHAR
     */
    public function __construct(
        private readonly PhpStanAutoloader $autoloader = new PhpStanAutoloader(),
    ) {}

    /**
     * Builds a container whose analysis reports what peq's collectors gathered.
     *
     * @param list<string> $files The files the analysis will cover
     *
     * @return Container The configured PHPStan container
     *
     * @throws RuntimeException If the working directory cannot be determined, if PHPStan
     *                          is not loadable, or if the generated configuration cannot be written
     */
    public function create(array $files): Container
    {
        $this->autoloader->ensureRegistered();

        $workingDirectory = getcwd();
        if ($workingDirectory === false) {
            throw new RuntimeException('Unable to determine current working directory');
        }

        $scratch = ScratchDirectory::create('peq-phpstan-');

        try {
            $configuration = $scratch->write('phpstan.neon', Yaml::dump([
                'services' => [
                    ['class' => DependencyCollector::class, 'tags' => ['phpstan.collector']],
                    ['class' => InClassMethodCollector::class, 'tags' => ['phpstan.collector']],
                ],
                'parameters' => [
                    'customRulesetUsed' => true,
                    'level' => self::COLLECTOR_ONLY_LEVEL,
                    'tmpDir' => $scratch->path.'/tmp',
                ],
                'includes' => [],
            ], 4));

            return (new PhpStanContainerFactory($workingDirectory))
                ->create($scratch->path, [$configuration], $files)
            ;
        } finally {
            $scratch->delete();
        }
    }
}
