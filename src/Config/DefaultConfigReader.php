<?php

declare(strict_types=1);

namespace App\Config;

use Override;

/**
 * Provides default configuration values.
 *
 * This reader returns the application's default configuration values, which serve
 * as the baseline for all configuration settings. These defaults are overridden by
 * values from other configuration sources (YAML files, environment variables, CLI args)
 * when merged by the ConfigLoader. It should typically be registered first in the
 * ConfigLoader's reader chain to establish a foundation of sensible defaults.
 *
 * The PHP version of the analysed sources is among them, so that an analysis is
 * always run for a version someone can name rather than for whichever one the
 * analysis engine would have settled on unasked.
 *
 * One default is not the same for every command. A walk is written as a tree, because
 * a person is reading it; a query answers with a table, because a table is what a
 * query answers with. That is the only difference between the two baselines, so it is
 * the only thing this reader is told.
 *
 * @phpstan-import-type ConfigFields from ConfigReader
 */
final class DefaultConfigReader implements ConfigReader
{
    /**
     * @param OutputFormat $output The format to report in when nothing else says
     */
    public function __construct(
        private readonly OutputFormat $output = OutputFormat::Tree,
    ) {}

    /**
     * Reports the baseline configuration every other source overlays.
     *
     * @return ConfigFields The default settings
     */
    #[Override]
    public function read(): array
    {
        return [
            'basePath' => '.',
            'direction' => 'uses',
            'level' => null,
            'output' => $this->output->value,
            'hops' => Config::HOPS,
            'includes' => [],
            'excludes' => [],
            'phpVersion' => PhpVersion::host()->toString(),
            'type' => AnalyzerKind::preferred()->value,
            'debug' => [
                'depth' => 5,
                'seed' => null,
            ],
        ];
    }
}
