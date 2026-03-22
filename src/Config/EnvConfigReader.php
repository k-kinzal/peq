<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Reads configuration from environment variables.
 *
 * This reader extracts configuration values from environment variables with a
 * specific prefix (default: 'PEQ_'). Variable names are converted from SCREAMING_SNAKE_CASE
 * to camelCase to match the Config class property names. This allows configuration
 * to be provided via environment variables in CI/CD pipelines or containerized environments.
 */
final class EnvConfigReader implements ConfigReader
{
    /**
     * @param string $prefix The prefix for environment variables to read (e.g., 'PEQ_')
     */
    public function __construct(
        private readonly string $prefix = 'PEQ_'
    ) {}

    /**
     * {@inheritdoc}
     */
    public function read(): array
    {
        $config = [];
        foreach (getenv() as $name => $value) {
            if (!str_starts_with($name, $this->prefix)) {
                continue;
            }

            $name = ltrim($name, $this->prefix);
            $name = strtolower($name);

            if (str_starts_with($name, 'debug_')) {
                $subKey = substr($name, 6);
                if (!isset($config['debug']) || !is_array($config['debug'])) {
                    $config['debug'] = [];
                }
                $config['debug'][$subKey] = $value;

                continue;
            }

            $name = preg_replace_callback('/_([a-z])/', function ($matches) {
                return strtoupper($matches[1]);
            }, $name);

            if (!is_string($name)) {
                continue;
            }

            $config[$name] = $value;
        }

        return $config;
    }
}
