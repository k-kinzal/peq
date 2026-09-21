<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use App\Action\AnalyzerChoice;
use App\Action\Query\QueryAction;
use App\Action\Query\QueryActionInput;
use App\Action\Query\QueryActionOutput;
use App\Analyzer\DebugAnalyzer\DebugAnalyzer;
use App\Analyzer\DebugAnalyzer\Generator\RandomSource;
use App\Analyzer\Graph\Direction;
use App\Command\GraphCommand;
use App\Config\AnalyzerKind;
use App\Config\Config;
use App\Config\ConfigException;
use App\Config\DebugAnalyzerConfig;
use App\Config\OutputFormat;
use App\Config\RawConfig;
use App\Gql\Element\GraphProjection;
use App\Gql\Element\GraphSchema;
use App\Gql\Execution\QueryExecution;
use App\Gql\Result\ResultTable;
use App\Reporter\Query\QueryReporterFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(GraphCommand::class)]
#[UsesClass(AnalyzerChoice::class)]
#[UsesClass(QueryAction::class)]
#[UsesClass(QueryActionInput::class)]
#[UsesClass(QueryActionOutput::class)]
#[UsesClass(DebugAnalyzer::class)]
#[UsesClass(RandomSource::class)]
#[UsesClass(GraphProjection::class)]
#[UsesClass(GraphSchema::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryReporterFactory::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigException::class)]
#[UsesClass(RawConfig::class)]
#[UsesClass(AnalyzerKind::class)]
#[UsesClass(OutputFormat::class)]
#[UsesClass(DebugAnalyzerConfig::class)]
#[UsesClass(Direction::class)]
#[UsesClass(ResultTable::class)]
#[Medium]
final class GraphCommandTest extends TestCase
{
    public function testConfigureDeclaresTheQueryToRunAsOptionalSoTheSchemaCanBeAskedForInstead(): void
    {
        self::assertFalse((new GraphCommand(new QueryAction()))->getDefinition()->getArgument('query')->isRequired());
    }

    public function testConfigureDeclaresEveryOptionTheConfigurationSourcesRead(): void
    {
        self::assertSame(
            ['schema', 'config', 'output', 'hops', 'include', 'exclude', 'type', 'debug-depth', 'debug-seed', 'memory-limit'],
            array_keys((new GraphCommand(new QueryAction()))->getDefinition()->getOptions()),
        );
    }

    public function testConfigureDescribesTheHopsAsTheLargestUpperBoundAQuantifierMayBeWrittenWith(): void
    {
        self::assertSame(
            'The largest upper bound a quantifier may be written with (default: 10)',
            (new GraphCommand(new QueryAction()))->getDefinition()->getOption('hops')->getDescription(),
        );
    }

    public function testExecuteWritesWhatTheQueryAnswered(): void
    {
        $tester = new CommandTester(new GraphCommand(new QueryAction()));
        $tester->execute([
            'query' => 'MATCH (p:Method) RETURN count(*) AS n',
            '--type' => 'debug',
            '--debug-seed' => '42',
            '--debug-depth' => '3',
            '--config' => __DIR__.'/absent.yaml',
        ]);

        self::assertSame(
            "+-----------+\n| n (INT64) |\n+-----------+\n| 11        |\n+-----------+\n",
            $tester->getDisplay(),
        );
    }

    public function testExecuteWritesTheAnswerInTheFormatTheReaderAsked(): void
    {
        $tester = new CommandTester(new GraphCommand(new QueryAction()));
        $tester->execute([
            'query' => 'MATCH (p:Method) RETURN count(*) AS n',
            '--type' => 'debug',
            '--debug-seed' => '42',
            '--debug-depth' => '3',
            '--output' => 'json',
            '--config' => __DIR__.'/absent.yaml',
        ]);

        self::assertSame(
            '{"status":"00000","condition":"note: successful completion","columns":[{"name":"n","type":"INT64"}],"rows":[[11]]}'."\n",
            $tester->getDisplay(),
        );
    }

    public function testExecuteSucceedsForAQueryThatFoundNothing(): void
    {
        $tester = new CommandTester(new GraphCommand(new QueryAction()));
        $status = $tester->execute([
            'query' => "MATCH (p:Method WHERE p.name = 'nothingIsCalledThis') RETURN p",
            '--type' => 'debug',
            '--debug-seed' => '42',
            '--debug-depth' => '3',
            '--config' => __DIR__.'/absent.yaml',
        ]);

        self::assertSame(Command::SUCCESS, $status);
    }

    public function testExecuteRunsAQuantifierWrittenUpToTheHopsItWasGiven(): void
    {
        $tester = new CommandTester(new GraphCommand(new QueryAction()));
        $status = $tester->execute([
            'query' => 'MATCH (a)-[e]->{1,3}(b) RETURN count(*) AS n',
            '--hops' => '3',
            '--type' => 'debug',
            '--debug-seed' => '42',
            '--debug-depth' => '3',
            '--config' => __DIR__.'/absent.yaml',
        ]);

        self::assertSame(Command::SUCCESS, $status);
    }

    public function testExecuteRefusesAQuantifierWrittenBeyondTheHopsItWasGiven(): void
    {
        $tester = new CommandTester(new GraphCommand(new QueryAction()));
        $status = $tester->execute([
            'query' => 'MATCH (a)-[e]->{1,4}(b) RETURN count(*) AS n',
            '--hops' => '3',
            '--type' => 'debug',
            '--debug-seed' => '42',
            '--debug-depth' => '3',
            '--config' => __DIR__.'/absent.yaml',
        ]);

        self::assertSame(Command::FAILURE, $status);
    }

    public function testExecuteSaysHowToRaiseTheHopsAQuantifierWentBeyond(): void
    {
        $tester = new CommandTester(new GraphCommand(new QueryAction()));
        $tester->execute([
            'query' => 'MATCH (a)-[e]->{1,4}(b) RETURN count(*) AS n',
            '--hops' => '3',
            '--type' => 'debug',
            '--debug-seed' => '42',
            '--debug-depth' => '3',
            '--config' => __DIR__.'/absent.yaml',
        ]);

        self::assertSame(
            '[42001] error: syntax error or access rule violation - invalid syntax: a quantifier may be written with an upper bound of at most 3 here,'
            ." and one is written with 4: raise --hops to allow more\n",
            $tester->getDisplay(),
        );
    }

    public function testExecuteFollowsAQuantifierWithNoUpperBoundUnderARestrictorWhateverTheHops(): void
    {
        $tester = new CommandTester(new GraphCommand(new QueryAction()));
        $status = $tester->execute([
            'query' => 'MATCH TRAIL (a)-[e]->{1,}(b) RETURN count(*) AS n',
            '--hops' => '1',
            '--type' => 'debug',
            '--debug-seed' => '42',
            '--debug-depth' => '3',
            '--config' => __DIR__.'/absent.yaml',
        ]);

        self::assertSame(Command::SUCCESS, $status);
    }

    public function testExecuteWritesTheVocabularyWhenAskedForItInsteadOfAQuery(): void
    {
        $tester = new CommandTester(new GraphCommand(new QueryAction()));
        $tester->execute([
            '--schema' => true,
            '--type' => 'debug',
            '--config' => __DIR__.'/absent.yaml',
        ]);

        self::assertStringContainsString("| node label        | Callable             |               |\n", $tester->getDisplay());
    }

    public function testExecuteAsksForAQueryWhenItIsGivenNeitherOne(): void
    {
        $tester = new CommandTester(new GraphCommand(new QueryAction()));
        $status = $tester->execute(['--type' => 'debug', '--config' => __DIR__.'/absent.yaml']);

        self::assertSame(Command::INVALID, $status);
    }

    public function testExecuteSaysWhatToWriteWhenItIsGivenNeitherOne(): void
    {
        $tester = new CommandTester(new GraphCommand(new QueryAction()));
        $tester->execute(['--type' => 'debug', '--config' => __DIR__.'/absent.yaml']);

        self::assertSame("Write the query to run, or ask for --schema to see what a query can be written against.\n", $tester->getDisplay());
    }

    public function testExecuteRejectsAConfigOptionThatIsNotAPath(): void
    {
        $tester = new CommandTester(new GraphCommand(new QueryAction()));
        $status = $tester->execute(['query' => 'RETURN 1 AS n', '--config' => ['a', 'b']]);

        self::assertSame(Command::INVALID, $status);
    }

    public function testExecuteReportsAQueryThatCannotBeRead(): void
    {
        $tester = new CommandTester(new GraphCommand(new QueryAction()));
        $status = $tester->execute([
            'query' => 'MATCH (p) RETRUN p',
            '--type' => 'debug',
            '--debug-seed' => '42',
            '--debug-depth' => '3',
            '--config' => __DIR__.'/absent.yaml',
        ]);

        self::assertSame(Command::FAILURE, $status);
    }

    public function testExecuteSaysWhereAQueryThatCannotBeReadWentWrong(): void
    {
        $tester = new CommandTester(new GraphCommand(new QueryAction()));
        $tester->execute([
            'query' => 'MATCH (p) RETRUN p',
            '--type' => 'debug',
            '--debug-seed' => '42',
            '--debug-depth' => '3',
            '--config' => __DIR__.'/absent.yaml',
        ]);

        self::assertSame(
            '[42001] error: syntax error or access rule violation - invalid syntax: expected a RETURN statement,'
            ." which is what GQL shows a query's answer with at line 1, column 11 (found \"RETRUN\")\n",
            $tester->getDisplay(),
        );
    }

    public function testExecuteReportsAConfigurationItCannotUse(): void
    {
        $tester = new CommandTester(new GraphCommand(new QueryAction()));
        $status = $tester->execute([
            'query' => 'RETURN 1 AS n',
            '--type' => 'nonsense',
            '--config' => __DIR__.'/absent.yaml',
        ]);

        self::assertSame(Command::FAILURE, $status);
    }

    public function testAnswerWritesTheVocabularyWhenThereIsNoQueryToRun(): void
    {
        $command = new GraphCommand(new QueryAction());
        $input = new ArrayInput(['--type' => 'debug'], $command->getDefinition());
        $output = new BufferedOutput();

        $command->answer($input, $output, __DIR__.'/absent.yaml', null);

        self::assertStringContainsString("| node label        | Callable             |               |\n", $output->fetch());
    }

    public function testAnswerReportsAConfigurationItCannotUse(): void
    {
        $command = new GraphCommand(new QueryAction());
        $input = new ArrayInput(['--type' => 'nonsense'], $command->getDefinition());

        self::assertSame(Command::FAILURE, $command->answer($input, new BufferedOutput(), __DIR__.'/absent.yaml', 'RETURN 1 AS n'));
    }

    public function testAnswerSaysWhichConfigurationItCannotUse(): void
    {
        $command = new GraphCommand(new QueryAction());
        $input = new ArrayInput(['--type' => 'nonsense'], $command->getDefinition());
        $output = new BufferedOutput();

        $command->answer($input, $output, __DIR__.'/absent.yaml', 'RETURN 1 AS n');

        self::assertSame("Invalid configuration \"type\": expected one of phpstan, native, debug, got \"nonsense\".\n", $output->fetch());
    }

    /**
     * @throws ConfigException
     */
    public function testWriteWritesTheAnswerInTheFormatTheConfigurationAsksFor(): void
    {
        $config = Config::fromArray(['basePath' => '.', 'direction' => 'uses', 'type' => 'debug', 'output' => 'json']);
        $output = new BufferedOutput();

        (new GraphCommand(new QueryAction()))->write($config, ResultTable::nothing(), null, $output);

        self::assertSame('{"status":"02000","condition":"note: no data","columns":[],"rows":[]}'."\n", $output->fetch());
    }
}
