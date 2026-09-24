<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use App\Action\Experimental\InspectVariablesAction;
use App\Command\ExperimentalCommand;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(ExperimentalCommand::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(InspectVariablesAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Action\Experimental\InspectVariablesInput::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\AnonymousClassNaming::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\AutoloadIndex::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\ClassLikeDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\DataFlow\Inspection::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\DataFlow\Slice::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\ExperimentAnalyzer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\Flow\Assignments::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\Flow\Branches::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\Flow\Exits::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\Flow\Expressions::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\Flow\Recording::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\Flow\State::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\Flow\Statements::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\Flow\Truth::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\ParsedSource::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\SourceIndex::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\PhpFileCollector::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\SourceParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Config\AnalyzerKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Config\Config::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Config\ConfigLoader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Config\DebugAnalyzerConfig::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Config\DefaultConfigReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Config\EnvConfigReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Config\ExperimentalConfigReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Config\InputConfigReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Config\OutputFormat::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Config\PhpVersion::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Config\RawConfig::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Config\YamlConfigLoader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Reporter\Experimental\VariableReporter::class)]
#[\PHPUnit\Framework\Attributes\UsesNamespace('App\Analyzer\ExperimentAnalyzer')]
#[\PHPUnit\Framework\Attributes\UsesNamespace('App\Action\Experimental')]
final class ExperimentalCommandTest extends TestCase
{
    public function testExecuteWritesAParseableExperimentalGraph(): void
    {
        $tester = new CommandTester(new ExperimentalCommand(new InspectVariablesAction()));
        $status = $tester->execute(['operation' => 'inspect', 'target' => 'Tests\Fixture\Experimental\Flow::calculate', 'path' => dirname(__DIR__, 2).'/Fixture/Experimental', '--line' => '15', '--variable' => 'value', '--output' => 'json', '--config' => __DIR__.'/absent.yaml']);
        self::assertSame(0, $status);
        self::assertJson($tester->getDisplay());
        self::assertStringContainsString('"experimental": true', $tester->getDisplay());
        self::assertStringContainsString('reaching-definition', $tester->getDisplay());
    }

    /**
     * @throws JsonException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerVariableTargets')]
    public function testExecuteSelectsEmbeddedVariablesWithoutALine(string $target, bool $reverse, int $line, string $kind): void
    {
        $tester = new CommandTester(new ExperimentalCommand(new InspectVariablesAction()));
        self::assertSame(0, $tester->execute(['operation' => 'inspect', 'target' => $target, 'path' => dirname(__DIR__, 2).'/Fixture/Experimental', '--direction' => 'uses', '--output' => 'json', '--config' => __DIR__.'/absent.yaml'] + ($reverse ? ['--reverse' => true] : [])));
        $result = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($result);
        self::assertIsArray($result['nodes']);
        self::assertIsArray($result['nodes'][0]);
        self::assertIsArray($result['provenance']);
        self::assertSame($line, $result['nodes'][0]['line']);
        self::assertSame($kind, $result['nodes'][0]['kind']);
        self::assertSame($reverse ? 'first-occurrence' : 'last-occurrence', $result['provenance']['selection']);
    }

    /**
     * @return iterable<array{string, bool, int, string}>
     */
    public static function providerVariableTargets(): iterable
    {
        yield ['Tests\Fixture\Experimental\Flow:calculate$copy', false, 18, 'read'];

        yield ['Tests\Fixture\Experimental\Flow:calculate$input', true, 9, 'parameter'];

        yield ['Tests\Fixture\Experimental\Properties:$sources', false, 15, 'property-access'];

        yield ['Tests\Fixture\Experimental\Properties:$sources', true, 9, 'property-declaration'];
    }

    public function testExecuteRequiresAVariableOrAnExplicitLine(): void
    {
        $tester = new CommandTester(new ExperimentalCommand(new InspectVariablesAction()));
        $status = $tester->execute(['operation' => 'inspect', 'target' => 'Example::method']);
        self::assertSame(1, $status);
        self::assertStringContainsString('Specify a variable in the target', $tester->getDisplay());
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testRequestRejectsUnknownOperations(): void
    {
        $command = new ExperimentalCommand(new InspectVariablesAction());
        $input = new \Symfony\Component\Console\Input\ArrayInput(['operation' => 'other', 'target' => 'Example::method'], $command->getDefinition());
        $this->expectException(\App\Action\Experimental\InspectionRejected::class);
        $command->request($input);
    }

    public function testConfigureDoesNotExposeTheExperimentAsAProductionAnalyzer(): void
    {
        $command = new ExperimentalCommand(new InspectVariablesAction());
        self::assertFalse($command->getDefinition()->hasOption('type'));
        self::assertTrue($command->getDefinition()->hasOption('line'));
        self::assertTrue($command->getDefinition()->hasOption('column'));
    }

    public function testExecuteSupportsTheReverseDirection(): void
    {
        $tester = new CommandTester(new ExperimentalCommand(new InspectVariablesAction()));
        $status = $tester->execute(['operation' => 'inspect', 'target' => 'Tests\Fixture\Experimental\Flow::calculate', 'path' => dirname(__DIR__, 2).'/Fixture/Experimental', '--line' => '15', '--variable' => 'copy', '--reverse' => true, '--output' => 'json', '--config' => __DIR__.'/absent.yaml']);
        self::assertSame(0, $status);
        self::assertStringContainsString('"direction": "used-by"', $tester->getDisplay());
        self::assertStringContainsString('"line": 18', $tester->getDisplay());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerDeclinedIssue')]
    public function testIssueNeverSendsWithoutExplicitYes(string $answer, bool $interactive): void
    {
        \org\bovigo\vfs\vfsStream::setup('report', null, ['result.json' => '{"experimental":true,"schemaVersion":2,"target":"f","analysis":{"status":"resolved","complete":true}}']);
        $sender = $this->createMock(\App\Action\Experimental\IssueSender::class);
        $sender->expects(self::never())->method('send');
        $tester = new CommandTester(new ExperimentalCommand(new InspectVariablesAction(), issues: new \App\Action\Experimental\IssueAction($sender)));
        $tester->setInputs([$answer]);
        self::assertSame(0, $tester->execute(['operation' => 'issue', 'target' => 'vfs://report/result.json'], ['interactive' => $interactive]));
        self::assertStringContainsString('Nothing was sent.', $tester->getDisplay());
        self::assertStringContainsString('Experimental analysis: f', $tester->getDisplay());
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerDeclinedIssue(): iterable
    {
        yield 'default No' => ['', true];

        yield 'explicit No' => ['no', true];

        yield 'not a Yes word' => ['yeti', true];

        yield 'noninteractive cannot consent' => ['yes', false];
    }

    /**
     * @throws JsonException
     */
    public function testIssueSendsExactlyThePreviewAfterYes(): void
    {
        \org\bovigo\vfs\vfsStream::setup('report', null, ['result.json' => '{"experimental":true,"schemaVersion":2,"target":"f","analysis":{"status":"resolved","complete":true}}']);
        $sender = $this->createMock(\App\Action\Experimental\IssueSender::class);
        $action = new \App\Action\Experimental\IssueAction($sender);
        $draft = $action->prepare('vfs://report/result.json');
        $sender->expects(self::once())->method('send')->with(self::equalTo($draft))->willReturn('https://example.test/issue');
        $tester = new CommandTester(new ExperimentalCommand(new InspectVariablesAction(), issues: $action));
        $tester->setInputs(['yes']);
        self::assertSame(0, $tester->execute(['operation' => 'issue', 'target' => 'vfs://report/result.json'], ['interactive' => true]));
        self::assertStringContainsString($draft->body, $tester->getDisplay());
        self::assertStringContainsString('https://example.test/issue', $tester->getDisplay());
    }

    public function testExecuteStrictReportsUnknownAsExitTwoAndStillWritesJson(): void
    {
        $tester = new CommandTester(new ExperimentalCommand(new InspectVariablesAction()));
        self::assertSame(2, $tester->execute(['operation' => 'inspect', 'target' => 'Tests\Fixture\Experimental\Flow::invalid', 'path' => dirname(__DIR__, 2).'/Fixture/Experimental', '--line' => '23', '--output' => 'json', '--strict' => true, '--config' => __DIR__.'/absent.yaml']));
        self::assertJson($tester->getDisplay());
        self::assertStringContainsString('"complete": false', $tester->getDisplay());
    }
}
