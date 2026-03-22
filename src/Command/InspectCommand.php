<?php

declare(strict_types=1);

namespace App\Command;

use App\Action\Inspect\InspectAction;
use App\Action\Inspect\InspectActionInput;
use App\Config\ConfigLoader;
use App\Config\DefaultConfigReader;
use App\Config\EnvConfigReader;
use App\Config\InputConfigReader;
use App\Config\YamlConfigLoader;
use App\Reporter\Traversal\DependencyTraversal;
use App\Reporter\Traversal\DependsTraversal;
use App\Reporter\TreeReporter\TreeReporter;
use App\Reporter\TreeReporter\TreeReporterOptions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to inspect the dependency tree of a PHP function, method, or class.
 *
 * This command analyzes the codebase and visualizes the dependency graph
 * for a specified target symbol. It supports filtering, depth control,
 * and different traversal directions (uses vs. used-by).
 */
#[AsCommand(
    name: 'inspect',
    description: 'Show dependency tree of a PHP function/method/class.',
    hidden: false,
)]
final class InspectCommand extends Command
{
    /**
     * @param InspectAction $action The action to execute for inspection
     */
    public function __construct(
        private readonly InspectAction $action,
    ) {
        parent::__construct();
    }

    /**
     * Configures the command options and arguments.
     */
    protected function configure(): void
    {
        $this->addOption(
            'config',
            null,
            InputOption::VALUE_REQUIRED,
            'Path to config file (default: <cwd>/.peq.yaml)',
            getcwd().'/.peq.yaml',
        );
        $this->addOption(
            'direction',
            'D',
            InputOption::VALUE_REQUIRED,
            'Dependency direction: uses|used-by (default: uses)',
            'uses',
        )->addOption(
            'level',
            'L',
            InputOption::VALUE_REQUIRED,
            'Limit depth of the dependency graph',
        )->addOption(
            'reverse',
            'R',
            InputOption::VALUE_NONE,
            'Shortcut for --direction used-by',
        )->addOption(
            'include',
            'I',
            InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
            'Include patterns',
            [],
        )->addOption(
            'exclude',
            'E',
            InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
            'Exclude patterns',
            [],
        )->addOption(
            'type',
            null,
            InputOption::VALUE_REQUIRED,
            'Analyzer type (phpstan|debug)',
            'phpstan',
        )->addOption(
            'debug-depth',
            null,
            InputOption::VALUE_REQUIRED,
            'Debug analyzer depth',
        )->addOption(
            'debug-seed',
            null,
            InputOption::VALUE_REQUIRED,
            'Debug analyzer seed',
        );
        $this->addArgument(
            'target',
            InputArgument::REQUIRED,
            'Namespace\ClassName, Namespace\ClassName::methodName or Namespace\functionName',
        );
        $this->addArgument(
            'path',
            InputArgument::OPTIONAL,
            'Base directory to analyze (default: current working dir)',
            getcwd(),
        );
    }

    /**
     * Executes the inspection command.
     *
     * @param InputInterface  $input  The input interface
     * @param OutputInterface $output The output interface
     *
     * @return int The command exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $target = $input->getArgument('target');
        if (!is_string($target)) {
            $output->writeln('<error>Target argument must be a string</error>');

            return Command::INVALID;
        }

        $path = $input->getOption('config');
        if (!is_string($path)) {
            $output->writeln('<error>Path argument must be a string</error>');

            return Command::INVALID;
        }

        $loader = new ConfigLoader([
            new DefaultConfigReader(),
            new EnvConfigReader(),
            new YamlConfigLoader($path),
            new InputConfigReader($input),
        ]);
        $config = $loader->load();

        $result = $this->action->execute(new InspectActionInput(
            config: $config,
            target: $target,
        ));

        $reporter = new TreeReporter(
            options: new TreeReporterOptions(
                level: $config->level,
            ),
            traversal: $config->direction === 'uses'
                ? new DependencyTraversal()
                : new DependsTraversal(),
        );
        $symbol = $config->direction === 'uses'
            ? $result->graph->nodes()[0]
            : $result->graph->nodes()[count($result->graph->nodes()) - 1];
        $reporter->report($result->graph, $symbol->id(), $output);

        return Command::SUCCESS;
    }
}
