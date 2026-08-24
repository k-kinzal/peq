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
     * @return array<string, mixed> The settings the user typed, as they were typed
     */
    public function read(): array
    {
        $config = [];

        $path = $this->input->getArgument('path');
        if (is_string($path)) {
            $config['basePath'] = $path;
        }

        if ($this->input->hasParameterOption(['--direction', '-D'])) {
            $config['direction'] = $this->input->getOption('direction');
        }
        if ($this->input->hasParameterOption(['--reverse', '-R'])) {
            $config['direction'] = Direction::UsedBy->value;
        }
        if ($this->input->hasParameterOption(['--level', '-L'])) {
            $config['level'] = $this->input->getOption('level');
        }
        if ($this->input->hasParameterOption(['--include', '-I'])) {
            $config['includes'] = $this->input->getOption('include');
        }
        if ($this->input->hasParameterOption(['--exclude', '-E'])) {
            $config['excludes'] = $this->input->getOption('exclude');
        }
        if ($this->input->hasParameterOption(['--type'])) {
            $config['type'] = $this->input->getOption('type');
        }

        $debug = [];
        if ($this->input->hasParameterOption(['--debug-depth'])) {
            $debug['depth'] = $this->input->getOption('debug-depth');
        }
        if ($this->input->hasParameterOption(['--debug-seed'])) {
            $debug['seed'] = $this->input->getOption('debug-seed');
        }
        if ($debug !== []) {
            $config['debug'] = $debug;
        }

        return $config;
    }
}
