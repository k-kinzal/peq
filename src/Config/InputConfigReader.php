<?php

declare(strict_types=1);

namespace App\Config;

use App\Analyzer\Graph\Direction;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Reads configuration from CLI command input.
 *
 * Only options the user actually typed are reported. A console option carries a
 * default whether or not it was given, so reporting every option would let the
 * command line silently overrule the configuration file and the environment for
 * settings the user never mentioned.
 *
 * What the user typed is reported as typed. An option that has to be a number is
 * not checked here: it is checked where every source's value for that setting is
 * checked, so `--level abc`, `PEQ_LEVEL=abc` and `level: abc` all fail the same way.
 *
 * @phpstan-import-type ConfigLeaf from ConfigReader
 * @phpstan-import-type ConfigFields from ConfigReader
 */
final class InputConfigReader implements ConfigReader
{
    /**
     * @param InputInterface $input The console input to read from
     */
    public function __construct(
        private readonly InputInterface $input,
    ) {}

    /**
     * Reports the configuration the command line carries.
     *
     * @return ConfigFields The settings the user typed, as they were typed
     */
    public function read(): array
    {
        $config = [];

        $path = $this->input->getArgument('path');
        if (is_string($path)) {
            $config['basePath'] = $path;
        }

        if ($this->input->hasParameterOption(['--direction', '-D'])) {
            $config['direction'] = $this->option('direction');
        }
        if ($this->input->hasParameterOption(['--reverse', '-R'])) {
            $config['direction'] = Direction::UsedBy->value;
        }
        if ($this->input->hasParameterOption(['--level', '-L'])) {
            $config['level'] = $this->option('level');
        }
        if ($this->input->hasParameterOption(['--include', '-I'])) {
            $config['includes'] = $this->option('include');
        }
        if ($this->input->hasParameterOption(['--exclude', '-E'])) {
            $config['excludes'] = $this->option('exclude');
        }
        if ($this->input->hasParameterOption(['--type'])) {
            $config['type'] = $this->option('type');
        }

        $debug = [];
        if ($this->input->hasParameterOption(['--debug-depth'])) {
            $debug['depth'] = $this->option('debug-depth');
        }
        if ($this->input->hasParameterOption(['--debug-seed'])) {
            $debug['seed'] = $this->option('debug-seed');
        }
        if ($debug !== []) {
            $config['debug'] = $debug;
        }

        return $config;
    }

    /**
     * Reads one console option as the setting it reports.
     *
     * Symfony reports an option as whatever the definition allows it to be, which is
     * a value, a repeated value, or the absence of one. That is the medium's whole
     * vocabulary, so anything else means the option was defined as something no
     * setting can be written as, and is reported here rather than carried inward.
     *
     * @param string $name The option name, without its leading dashes
     *
     * @return ConfigLeaf|list<ConfigLeaf> What the user typed for it
     *
     * @throws ConfigException If the option carries something no setting can be
     */
    public function option(string $name): array|bool|float|int|string|null
    {
        $value = $this->input->getOption($name);
        if (!is_array($value)) {
            return $this->reported($name, $value);
        }

        $values = [];
        foreach ($value as $item) {
            $values[] = $this->reported($name, $item);
        }

        return $values;
    }

    /**
     * Reads one value a console option carries.
     *
     * @param string $name  The option name it was typed for
     * @param mixed  $value What Symfony reported for it
     *
     * @return ConfigLeaf The value
     *
     * @throws ConfigException If it is not something a setting can be
     */
    public function reported(string $name, mixed $value): bool|float|int|string|null
    {
        if ($value !== null && !is_scalar($value)) {
            throw new ConfigException(sprintf(
                'Invalid configuration "--%s": expected a setting, got %s',
                $name,
                get_debug_type($value),
            ));
        }

        return $value;
    }
}
