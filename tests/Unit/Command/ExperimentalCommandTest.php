<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use App\Action\Experimental\InspectVariablesAction;
use App\Command\ExperimentalCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(ExperimentalCommand::class)]
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

    public function testExecuteRequiresALine(): void
    {
        $tester = new CommandTester(new ExperimentalCommand(new InspectVariablesAction()));
        $status = $tester->execute(['operation' => 'inspect', 'target' => 'Example::method']);
        self::assertSame(1, $status);
        self::assertStringContainsString('--line option is required', $tester->getDisplay());
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
}
