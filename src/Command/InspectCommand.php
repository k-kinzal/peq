<?php

declare(strict_types=1);

namespace App\Command;

use App\Action\Inspect\InspectAction;
use App\Action\Inspect\InspectActionInput;
use App\Action\Inspect\SymbolNotFoundException;
use App\Config\AnalyzerKind;
use App\Config\ConfigException;
use App\Config\ConfigLoader;
use App\Config\DefaultConfigReader;
use App\Config\EnvConfigReader;
use App\Config\InputConfigReader;
use App\Config\YamlConfigLoader;
use App\Reporter\ReporterFactory;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to inspect the dependency tree of a PHP function, method, or class.
 *
 * The command is the input and output boundary. It declares the command line, stacks
 * the configuration sources, runs the inspection and hands the result to the reporter
 * the configuration asks for. It decides nothing about analysis or presentation: which
 * analyzer runs is the use case's decision, and which reporter renders is the reporter
 * factory's, so this class holds no branch on either.
 */
#[AsCommand(
    name: 'inspect',
    description: 'Show dependency tree of a PHP function/method/class.',
    hidden: false,
)]
final class InspectCommand extends Command
{
    /**
     * @param InspectAction   $action    The inspection to run
     * @param ReporterFactory $reporters Chooses the reporter the configuration asks for
     */
    public function __construct(
        private readonly InspectAction $action,
        private readonly ReporterFactory $reporters = new ReporterFactory(),
    ) {
        parent::__construct();
    }

    /**
     * Declares the arguments and options the command accepts.
     */
    #[Override]
    protected function configure(): void
    {
        $this->setDefinition(new InputDefinition([
            new InputArgument('target', InputArgument::REQUIRED, 'Namespace\ClassName, Namespace\ClassName::methodName or Namespace\functionName'),
            new InputArgument('path', InputArgument::OPTIONAL, 'Base directory to analyze (default: current working dir)'),
            new InputOption('config', null, InputOption::VALUE_REQUIRED, 'Path to config file (default: <cwd>/.peq.yaml)', getcwd().'/.peq.yaml'),
            new InputOption('direction', 'D', InputOption::VALUE_REQUIRED, 'Dependency direction: uses|used-by (default: uses)'),
            new InputOption('level', 'L', InputOption::VALUE_REQUIRED, 'Limit depth of the dependency graph'),
            new InputOption('reverse', 'R', InputOption::VALUE_NONE, 'Shortcut for --direction used-by'),
            new InputOption('include', 'I', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Include patterns', []),
            new InputOption('exclude', 'E', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Exclude patterns', []),
            new InputOption('type', null, InputOption::VALUE_REQUIRED, sprintf('Analyzer type (%s)', AnalyzerKind::spellAvailable())),
            new InputOption('debug-depth', null, InputOption::VALUE_REQUIRED, 'Debug analyzer depth'),
            new InputOption('debug-seed', null, InputOption::VALUE_REQUIRED, 'Debug analyzer seed'),
            new InputOption('memory-limit', null, InputOption::VALUE_REQUIRED, 'Memory limit (e.g. 1G, 256M)'),
        ]));
    }

    /**
     * Runs the inspection and writes its report.
     *
     * @param InputInterface  $input  The parsed command line
     * @param OutputInterface $output Where the report and any error is written
     *
     * @return int Command::SUCCESS, Command::INVALID for unusable arguments, or
     *             Command::FAILURE when the configuration or the symbol is rejected
     */
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $memoryLimit = $input->getOption('memory-limit');
        if (is_string($memoryLimit)) {
            ini_set('memory_limit', $memoryLimit);
        }

        $target = $input->getArgument('target');
        if (!is_string($target)) {
            $output->writeln('<error>The target must be written as a single symbol name.</error>');

            return Command::INVALID;
        }

        $configPath = $input->getOption('config');
        if (!is_string($configPath)) {
            $output->writeln('<error>The --config option must be a path to a file.</error>');

            return Command::INVALID;
        }

        try {
            $config = (new ConfigLoader([
                new DefaultConfigReader(),
                new YamlConfigLoader($configPath),
                new EnvConfigReader(),
                new InputConfigReader($input),
            ]))->load();

            $result = $this->action->execute(new InspectActionInput(
                config: $config,
                target: $target,
            ));
        } catch (ConfigException|SymbolNotFoundException $rejected) {
            $output->writeln(sprintf('<error>%s</error>', $rejected->getMessage()));

            return Command::FAILURE;
        }

        $this->reporters->create($config)->report($result->graph, $result->symbol->id(), $output);

        return Command::SUCCESS;
    }
}
