<?php

declare(strict_types=1);

namespace App\Config;

use Symfony\Component\Console\Input\InputInterface;

/**
 * Reads configuration from CLI command input.
 *
 * This reader extracts only explicitly-provided configuration values from the
 * Symfony Console InputInterface. Options and arguments that were not specified
 * by the user are omitted, ensuring that CLI defaults do not override values
 * from lower-priority configuration sources (YAML files, environment variables).
 */
final class InputConfigReader implements ConfigReader
{
    /**
     * @param InputInterface $input The Symfony Console input interface to read from
     */
    public function __construct(
        private readonly InputInterface $input,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function read(): array
    {
        $config = [];

        $path = $this->input->getArgument('path');
        if (is_string($path)) {
            $config['basePath'] = $path;
        }

        if ($this->hasParameter('--direction', '-D')) {
            $config['direction'] = $this->input->getOption('direction');
        }

        if ($this->hasParameter('--reverse', '-R')) {
            $config['direction'] = 'used-by';
        }

        if ($this->hasParameter('--level', '-L')) {
            $levelValue = $this->input->getOption('level');
            $level = filter_var($levelValue, FILTER_VALIDATE_INT);
            if ($level === false || $level <= 0) {
                $displayValue = is_scalar($levelValue) ? (string) $levelValue : get_debug_type($levelValue);

                throw new ConfigException(sprintf('Invalid level: %s. Level must be a positive integer.', $displayValue));
            }
            $config['level'] = $level;
        }

        if ($this->hasParameter('--include', '-I')) {
            $config['includes'] = $this->input->getOption('include');
        }

        if ($this->hasParameter('--exclude', '-E')) {
            $config['excludes'] = $this->input->getOption('exclude');
        }

        if ($this->hasParameter('--type')) {
            $config['type'] = $this->input->getOption('type');
        }

        if ($this->hasParameter('--debug-depth') || $this->hasParameter('--debug-seed')) {
            $config['debug'] = [];
            if ($this->hasParameter('--debug-depth')) {
                $depth = filter_var($this->input->getOption('debug-depth'), FILTER_VALIDATE_INT);
                if ($depth !== false) {
                    $config['debug']['depth'] = $depth;
                }
            }
            if ($this->hasParameter('--debug-seed')) {
                $seed = filter_var($this->input->getOption('debug-seed'), FILTER_VALIDATE_INT);
                if ($seed !== false) {
                    $config['debug']['seed'] = $seed;
                }
            }
        }

        return $config;
    }

    private function hasParameter(string ...$names): bool
    {
        return $this->input->hasParameterOption($names);
    }
}
