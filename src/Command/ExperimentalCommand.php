<?php

declare(strict_types=1);

namespace App\Command;

use App\Action\Experimental\InspectionRejected;
use App\Action\Experimental\InspectVariablesAction;
use App\Action\Experimental\InspectVariablesInput;
use App\Config\ConfigException;
use App\Config\ConfigLoader;
use App\Config\DefaultConfigReader;
use App\Config\EnvConfigReader;
use App\Config\ExperimentalConfigReader;
use App\Config\InputConfigReader;
use App\Config\OutputFormat;
use App\Config\YamlConfigLoader;
use App\Reporter\Experimental\VariableReporter;
use JsonException;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The explicit opt-in boundary for experimental source-occurrence inspection.
 */
#[AsCommand(name: 'experimental', description: 'Experimental local variable dependencies: experimental inspect <callable> --line <n>.')]
final class ExperimentalCommand extends Command
{
    /**
     * Creates this value with its explicit analysis inputs.
     */
    public function __construct(
        private readonly InspectVariablesAction $action,
        private readonly VariableReporter $reporter = new VariableReporter(),
        private readonly \App\Action\Experimental\IssueAction $issues = new \App\Action\Experimental\IssueAction(),
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('operation', InputArgument::REQUIRED, 'Experimental operation: inspect|issue');
        $this->addArgument('target', InputArgument::REQUIRED, 'Callable for inspect; saved JSON file for issue');
        $this->addArgument('path', InputArgument::OPTIONAL, 'File or directory to analyze');
        $this->addOption('line', null, InputOption::VALUE_REQUIRED, 'Source line to inspect (required)');
        $this->addOption('variable', null, InputOption::VALUE_REQUIRED, 'Variable name, with or without $; omit to select the whole line');
        $this->addOption('column', null, InputOption::VALUE_REQUIRED, 'Disambiguate occurrences on the same line (1-based byte column)');
        $this->addOption('config', null, InputOption::VALUE_REQUIRED, 'Configuration file', getcwd().'/.peq.yaml');
        $this->addOption('direction', 'D', InputOption::VALUE_REQUIRED, 'Dependency direction: uses|used-by');
        $this->addOption('reverse', 'R', InputOption::VALUE_NONE, 'Shortcut for --direction used-by');
        $this->addOption('level', 'L', InputOption::VALUE_REQUIRED, 'Maximum dependency edges from the selected occurrences');
        $this->addOption('output', 'O', InputOption::VALUE_REQUIRED, 'Output format: '.OutputFormat::spell());
        $this->addOption('include', 'I', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Include patterns', []);
        $this->addOption('exclude', 'E', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Exclude patterns', []);
        $this->addOption('php-version', null, InputOption::VALUE_REQUIRED, 'PHP version of the analyzed sources');
        $this->addOption('memory-limit', null, InputOption::VALUE_REQUIRED, 'Memory limit (e.g. 1G)');
        $this->addOption('strict', null, InputOption::VALUE_NONE, 'Exit 2 if analysis is incomplete or truncated');
        $this->addOption('include-source', null, InputOption::VALUE_NONE, 'Include source snippets in the issue preview');
        $this->addOption('description', null, InputOption::VALUE_REQUIRED, 'Expected and observed behavior for the issue', '');
        $this->setHelp('Arrows describe possible local data/control dependencies. Calls, heap state and closures are explicit boundaries. Unsupported regions remain Unknown. experimental issue result.json previews a report and asks before sending; the default is No. Use --column to distinguish reads and writes on the same line.');
    }

    /**
     * @throws JsonException
     */
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            if ($input->getArgument('operation') === 'issue') {
                return $this->issue($input, $output);
            }
            $request = $this->request($input);
            $result = $this->action->execute($request);
            $this->reporter->report($result, $request->config->output, $output);

            return $input->getOption('strict') === true && !$result->analysis->complete ? 2 : Command::SUCCESS;
        } catch (ConfigException|InspectionRejected $error) {
            $output->writeln('<error>'.OutputFormatter::escape($error->getMessage()).'</error>');

            return Command::FAILURE;
        }
    }

    /**
     * Previews exact content and requires an interactive, default-No confirmation.
     *
     * @throws InspectionRejected|JsonException If the draft cannot be prepared or sent
     */
    public function issue(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getArgument('target');
        $description = $input->getOption('description');
        if (!is_string($path) || !is_string($description)) {
            throw new InspectionRejected('Issue file and description must be strings.');
        }
        $draft = $this->issues->prepare($path, $input->getOption('include-source') === true, $description);
        $output->writeln('Repository: https://github.com/k-kinzal/peq'."\n".'Title: '.$draft->title."\n\n".$draft->body, OutputInterface::OUTPUT_RAW);
        $question = new \Symfony\Component\Console\Question\ConfirmationQuestion('Send this issue? [y/N] ', false, '/^(?:y|yes)$/i');
        $confirmed = $input->isInteractive() && (new \Symfony\Component\Console\Helper\QuestionHelper())->ask($input, $output, $question) === true;
        if (!$confirmed) {
            $output->writeln('Nothing was sent.');

            return Command::SUCCESS;
        }
        $output->writeln($this->issues->send($draft), OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }

    /**
     * Parses the experimental operation, coordinates and stacked configuration.
     *
     * @throws ConfigException|InspectionRejected If the requested analysis or encoding is rejected
     */
    public function request(InputInterface $input): InspectVariablesInput
    {
        if ($input->getArgument('operation') !== 'inspect') {
            throw new InspectionRejected('Unknown experimental operation. Use "peq experimental inspect".');
        }
        $memory = $input->getOption('memory-limit');
        if (is_string($memory)) {
            ini_set('memory_limit', $memory);
        }
        $target = $input->getArgument('target');
        $configPath = $input->getOption('config');
        if (!is_string($target) || !is_string($configPath)) {
            throw new InspectionRejected('The target and configuration path must be strings.');
        }
        $reader = new InputConfigReader($input);
        $line = ExperimentalConfigReader::coordinate($reader, 'line');
        if ($line === null) {
            throw new InspectionRejected('The --line option is required.');
        }
        $variable = $input->getOption('variable');
        if ($variable !== null && (!is_string($variable) || preg_match('/^\$?[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/D', $variable) !== 1)) {
            throw new InspectionRejected('The --variable option must name a PHP variable.');
        }
        $config = (new ConfigLoader([
            new DefaultConfigReader(), new YamlConfigLoader($configPath), new EnvConfigReader(),
            $reader, new ExperimentalConfigReader(),
        ]))->load();

        return new InspectVariablesInput($config, $target, $line, $variable, ExperimentalConfigReader::coordinate($reader, 'column'));
    }
}
