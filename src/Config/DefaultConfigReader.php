<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Provides default configuration values.
 *
 * This reader returns the application's default configuration values, which serve
 * as the baseline for all configuration settings. These defaults are overridden by
 * values from other configuration sources (YAML files, environment variables, CLI args)
 * when merged by the ConfigLoader. It should typically be registered first in the
 * ConfigLoader's reader chain to establish a foundation of sensible defaults.
 */
final class DefaultConfigReader implements ConfigReader
{
    /**
     * {@inheritdoc}
     */
    public function read(): array
    {
        return [
            'basePath' => '.',
            'direction' => 'uses',
            'level' => null,
            'includes' => [],
            'excludes' => [],
            'type' => 'debug',
            'debug' => [
                'depth' => 5,
                'seed' => null,
            ],
        ];
    }
}
