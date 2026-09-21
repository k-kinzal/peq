<?php

declare(strict_types=1);

namespace App\Command;

use App\Action\Query\QueryAction;
use App\Action\Query\QueryActionInput;
use App\Config\AnalyzerKind;
use App\Config\Config;
use App\Config\ConfigException;
use App\Config\ConfigLoader;
use App\Config\DefaultConfigReader;
use App\Config\EnvConfigReader;
use App\Config\InputConfigReader;
use App\Config\OutputFormat;
use App\Config\YamlConfigLoader;
use App\Gql\Element\ElementGraph;
use App\Gql\Element\GraphSchema;
use App\Gql\GqlException;
use App\Gql\Result\ResultTable;
use App\Reporter\Query\QueryReporterFactory;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to query the dependency graph in GQL.
 *
 * The root command answers one question — what does this symbol reach — in the shape
 * a person reads. This one answers whatever question the reader can write, in the
 * shape a program reads, and the two are deliberately different tools. A reviewer
 * wants a tree; an agent working out whether a change is safe wants to ask "which
 * public methods of a controller reach the cache, and on which lines", get a table
 * back, and act on it.
 *
 * GQL rather than an invented syntax, because the reader this exists for already
 * knows GQL: it is the ISO standard the SQL committee publishes, and a model that has
 * read about graph databases has read about it. A query language nobody has seen
 * before would have to be explained in every prompt.
 *
 * The failure of a query is reported the way GQL reports one, with the five-character
 * status first. A query that could not be read, one that named something that is not
 * there, and one that simply found nothing need three different next moves, and a
 * caller should not have to read English to tell them apart.
 */
#[AsCommand(
    name: 'graph',
    description: 'Query the dependency graph in GQL.',
    hidden: false,
)]
final class GraphCommand extends Command
{
    /**
     * @param QueryAction          $action    The query to run
     * @param QueryReporterFactory $reporters Chooses the reporter the configuration asks for
     */
    public function __construct(
        private readonly QueryAction $action,
        private readonly QueryReporterFactory $reporters = new QueryReporterFactory(),
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
            new InputArgument('query', InputArgument::OPTIONAL, 'The GQL query to run, for example: MATCH (m:Method)-[:methodCall]->(t) RETURN m.id, t.id'),
            new InputArgument('path', InputArgument::OPTIONAL, 'Base directory to analyze (default: current working dir)'),
            new InputOption('schema', null, InputOption::VALUE_NONE, 'Write the labels, properties and functions a query can use, instead of running one'),
            new InputOption('config', null, InputOption::VALUE_REQUIRED, 'Path to config file (default: <cwd>/.peq.yaml)', getcwd().'/.peq.yaml'),
            new InputOption('output', 'O', InputOption::VALUE_REQUIRED, sprintf('Output format (%s)', OutputFormat::spell())),
            new InputOption('hops', null, InputOption::VALUE_REQUIRED, sprintf('The largest upper bound a quantifier may be written with (default: %d)', Config::HOPS)),
            new InputOption('include', 'I', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Include patterns', []),
            new InputOption('exclude', 'E', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Exclude patterns', []),
            new InputOption('php-version', null, InputOption::VALUE_REQUIRED, 'PHP version the analyzed sources are read as (default: the version peq runs on)'),
            new InputOption('type', null, InputOption::VALUE_REQUIRED, sprintf('Analyzer type (%s)', AnalyzerKind::spellAvailable())),
            new InputOption('debug-depth', null, InputOption::VALUE_REQUIRED, 'Debug analyzer depth'),
            new InputOption('debug-seed', null, InputOption::VALUE_REQUIRED, 'Debug analyzer seed'),
            new InputOption('memory-limit', null, InputOption::VALUE_REQUIRED, 'Memory limit (e.g. 1G, 256M)'),
        ]));
    }

    /**
     * Runs the query and writes what it answered.
     *
     * A query that found nothing is a success, and says so through its exit status as
     * well as through the status code it reports: finding no method that reaches the
     * cache is an answer, and a caller that treated it as a failure would retry a
     * question that was already settled.
     *
     * @param InputInterface  $input  The parsed command line
     * @param OutputInterface $output Where the answer and any failure is written
     *
     * @return int Command::SUCCESS, Command::INVALID for unusable arguments, or
     *             Command::FAILURE when the configuration or the query is rejected
     */
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $memoryLimit = $input->getOption('memory-limit');
        if (is_string($memoryLimit)) {
            ini_set('memory_limit', $memoryLimit);
        }

        $configPath = $input->getOption('config');
        if (!is_string($configPath)) {
            $output->writeln('<error>The --config option must be a path to a file.</error>');

            return Command::INVALID;
        }

        $query = $input->getArgument('query');
        if ($input->getOption('schema') === true) {
            return $this->answer($input, $output, $configPath, null);
        }
        if (!is_string($query)) {
            $output->writeln('<error>Write the query to run, or ask for --schema to see what a query can be written against.</error>');

            return Command::INVALID;
        }

        return $this->answer($input, $output, $configPath, $query);
    }

    /**
     * Loads the configuration, answers the question and writes the answer.
     *
     * @param InputInterface  $input      The parsed command line
     * @param OutputInterface $output     Where the answer and any failure is written
     * @param string          $configPath Where the configuration file is
     * @param null|string     $query      The query to run, or null to write the vocabulary instead
     *
     * @return int Command::SUCCESS, or Command::FAILURE when the configuration or the query is rejected
     */
    public function answer(InputInterface $input, OutputInterface $output, string $configPath, ?string $query): int
    {
        try {
            $config = (new ConfigLoader([
                new DefaultConfigReader(OutputFormat::Table),
                new YamlConfigLoader($configPath),
                new EnvConfigReader(),
                new InputConfigReader($input),
            ]))->load();

            $answered = $query === null
                ? null
                : $this->action->execute(new QueryActionInput(config: $config, query: $query));
        } catch (ConfigException|GqlException $rejected) {
            $output->writeln(sprintf('<error>%s</error>', $rejected->getMessage()));

            return Command::FAILURE;
        }

        $this->write(
            $config,
            $answered === null ? GraphSchema::table() : $answered->result,
            $answered?->graph,
            $output,
        );

        return Command::SUCCESS;
    }

    /**
     * Writes what a query answered, in the format the configuration asks for.
     *
     * @param Config            $config The merged application configuration
     * @param ResultTable       $result What the query answered
     * @param null|ElementGraph $graph  The graph it was answered from, or null when there is none
     * @param OutputInterface   $output Where it is written
     */
    public function write(Config $config, ResultTable $result, ?ElementGraph $graph, OutputInterface $output): void
    {
        $this->reporters->create($config, $graph)->report($result, $output);
    }
}
