<?php

declare(strict_types=1);

namespace App\Config;

use Symfony\Component\Console\Input\InputInterface;

/**
 * Reads configuration from CLI command input.
 *
 * This reader extracts configuration values from the Symfony Console InputInterface,
 * merging both command arguments and options. It provides the highest priority in the
 * configuration cascade, allowing users to override all other configuration sources
 * (defaults, YAML files, environment variables) directly from the command line.
 */
final readonly class InputConfigReader implements ConfigReader
{
    /**
     * @param InputInterface $input The Symfony Console input interface to read from
     */
    public function __construct(
        private InputInterface $input,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function read(): array
    {
        $options = $this->input->getOptions();
        $options = array_filter($options, fn ($v, $k) => is_string($k), ARRAY_FILTER_USE_BOTH);
        $arguments = $this->input->getArguments();
        $arguments = array_filter($arguments, fn ($v, $k) => is_string($k), ARRAY_FILTER_USE_BOTH);

        $config = array_merge($options, $arguments);

        if (isset($config['path'])) {
            $config['basePath'] = $config['path'];
        }
        if (isset($config['include'])) {
            $config['includes'] = $config['include'];
        }
        if (isset($config['exclude'])) {
            $config['excludes'] = $config['exclude'];
        }
        if (isset($config['level'])) {
            $levelValue = $config['level'];
            $level = filter_var($levelValue, FILTER_VALIDATE_INT);
            if ($level === false || $level <= 0) {
                $displayValue = is_scalar($levelValue) ? (string) $levelValue : get_debug_type($levelValue);

                throw new ConfigException(sprintf('Invalid level: %s. Level must be a positive integer.', $displayValue));
            }
            $config['level'] = $level;
        }

        if (isset($config['debug-depth']) || isset($config['debug-seed'])) {
            if (!isset($config['debug']) || !is_array($config['debug'])) {
                $config['debug'] = [];
            }
            if (isset($config['debug-depth'])) {
                $depth = filter_var($config['debug-depth'], FILTER_VALIDATE_INT);
                if ($depth !== false) {
                    $config['debug']['depth'] = $depth;
                }
            }
            if (isset($config['debug-seed'])) {
                $seed = filter_var($config['debug-seed'], FILTER_VALIDATE_INT);
                if ($seed !== false) {
                    $config['debug']['seed'] = $seed;
                }
            }
        }

        return $config;
    }
}
