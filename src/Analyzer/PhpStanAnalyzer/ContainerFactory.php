<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer;

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
final readonly class ContainerFactory
{
    /**
     * Analysis level of the generated configuration.
     *
     * Collectors run at every level, and level 0 leaves out the type-checking rules
     * peq has no use for, so nothing is spent on diagnostics that are discarded.
     */
    private const int COLLECTOR_ONLY_LEVEL = 0;

    /**
     * @param PhpStanAutoloader $autoloader Makes PHPStan loadable when peq runs from a PHAR
     */
    public function __construct(
        private PhpStanAutoloader $autoloader = new PhpStanAutoloader(),
    ) {}

    /**
     * Builds a container whose analysis reports what the given collectors gathered.
     *
     * The configuration is written into the directory PHPStan works in, under a name
     * derived from its contents. PHPStan compiles a container class for each
     * configuration path it is given and loads it once per process, so an analysis
     * with the same collectors as an earlier one — in the next command, or in the
     * next test of a suite — starts from the class that one compiled instead of
     * compiling, loading and keeping another. The name carries the contents because
     * PHPStan also remembers what it read from a path for the rest of the process,
     * so a path must never be reused for a different configuration.
     *
     * The PHP version the sources are read as is written into that configuration
     * twice: once as the parameter PHPStan configures its own parser and its
     * version-dependent reflection from, and once as the argument of the re-parsed
     * source peq reads method bodies with. Both read the same files, so a version
     * that reached only one of them would leave the bodies of every file the other
     * cannot parse silently missing from the graph.
     *
     * @param list<string>       $files      The files the analysis will cover
     * @param list<class-string> $collectors The collectors the analysis runs
     * @param null|int           $phpVersion The PHP version the sources are analysed as, in
     *                                       PHP_VERSION_ID form, or null to leave the choice to PHPStan
     *
     * @return Container The configured PHPStan container
     *
     * @throws RuntimeException If the working directory cannot be determined, if PHPStan
     *                          is not loadable, or if the generated configuration cannot be written
     */
    public function create(array $files, array $collectors, ?int $phpVersion = null): Container
    {
        $this->autoloader->ensureRegistered();

        $workingDirectory = getcwd();
        if ($workingDirectory === false) {
            throw new RuntimeException('Unable to determine current working directory');
        }

        $services = array_map(
            static fn (string $collector): array => ['class' => $collector, 'tags' => ['phpstan.collector']],
            $collectors,
        );
        $services[] = ['class' => ReparsedSource::class, 'arguments' => ['phpVersion' => $phpVersion]];

        $parameters = [
            'customRulesetUsed' => true,
            'level' => self::COLLECTOR_ONLY_LEVEL,
        ];
        if ($phpVersion !== null) {
            $parameters['phpVersion'] = $phpVersion;
        }

        $neon = Yaml::dump([
            'services' => $services,
            'parameters' => $parameters,
            'includes' => [],
        ], 4);
        $directory = WorkingDirectory::shared();
        $configuration = $directory->write('phpstan-'.md5($neon).'.neon', $neon);

        return (new PhpStanContainerFactory($workingDirectory))
            ->create($directory->path, [$configuration], $files)
        ;
    }
}
