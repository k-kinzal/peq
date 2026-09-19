<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use App\Action\Inspect\InspectAction;
use App\Analyzer\DebugAnalyzer\DebugAnalyzer;
use App\Command\InspectCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(InspectCommand::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\RandomSource::class)]
#[Medium]
final class InspectCommandTest extends TestCase
{
    public function testConfigureDeclaresTheSymbolToInspectAsRequired(): void
    {
        self::assertTrue((new InspectCommand(new InspectAction()))->getDefinition()->getArgument('target')->isRequired());
    }

    public function testConfigureDeclaresThePathToAnalyseAsOptional(): void
    {
        self::assertFalse((new InspectCommand(new InspectAction()))->getDefinition()->getArgument('path')->isRequired());
    }

    public function testConfigureDeclaresEveryOptionTheConfigurationSourcesRead(): void
    {
        $definition = (new InspectCommand(new InspectAction()))->getDefinition();

        self::assertTrue($definition->hasOption('direction'));
        self::assertTrue($definition->hasOption('level'));
        self::assertTrue($definition->hasOption('reverse'));
        self::assertTrue($definition->hasOption('include'));
        self::assertTrue($definition->hasOption('exclude'));
        self::assertTrue($definition->hasOption('php-version'));
        self::assertTrue($definition->hasOption('type'));
        self::assertTrue($definition->hasOption('debug-depth'));
        self::assertTrue($definition->hasOption('debug-seed'));
        self::assertTrue($definition->hasOption('memory-limit'));
        self::assertTrue($definition->hasOption('config'));
    }

    public function testExecuteWritesTheTreeOfTheRequestedSymbol(): void
    {
        $root = (new DebugAnalyzer(seed: 42, depth: 3))->analyze('/generated')->nodes()[0]->id()->toString();
        $tester = new CommandTester(new InspectCommand(new InspectAction()));
        $tester->execute([
            'target' => $root,
            '--type' => 'debug',
            '--debug-seed' => '42',
            '--debug-depth' => '3',
            '--config' => __DIR__.'/absent.yaml',
        ]);

        self::assertStringContainsString($root, $tester->getDisplay());
    }

    public function testExecuteSucceedsWhenTheSymbolWasFound(): void
    {
        $root = (new DebugAnalyzer(seed: 42, depth: 3))->analyze('/generated')->nodes()[0]->id()->toString();
        $tester = new CommandTester(new InspectCommand(new InspectAction()));
        $status = $tester->execute([
            'target' => $root,
            '--type' => 'debug',
            '--debug-seed' => '42',
            '--debug-depth' => '3',
            '--config' => __DIR__.'/absent.yaml',
        ]);

        self::assertSame(Command::SUCCESS, $status);
    }

    public function testExecuteReportsAFailureWhenTheSymbolIsNotInTheGraph(): void
    {
        $tester = new CommandTester(new InspectCommand(new InspectAction()));
        $status = $tester->execute([
            'target' => 'App\Domain\NeverAnalysed',
            '--type' => 'debug',
            '--debug-seed' => '42',
            '--config' => __DIR__.'/absent.yaml',
        ]);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('App\Domain\NeverAnalysed', $tester->getDisplay());
    }

    public function testExecuteReportsAFailureWhenTheConfigurationIsRejected(): void
    {
        $tester = new CommandTester(new InspectCommand(new InspectAction()));
        $status = $tester->execute([
            'target' => 'App\Domain\Invoice',
            '--type' => 'debug',
            '--level' => 'not a number',
            '--config' => __DIR__.'/absent.yaml',
        ]);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('level', $tester->getDisplay());
    }

    public function testExecuteReportsAFailureWhenThePhpVersionToAnalyseIsOutsideTheSupportedRange(): void
    {
        $tester = new CommandTester(new InspectCommand(new InspectAction()));
        $status = $tester->execute([
            'target' => 'App\Domain\Invoice',
            '--type' => 'debug',
            '--php-version' => '5.6',
            '--config' => __DIR__.'/absent.yaml',
        ]);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('phpVersion', $tester->getDisplay());
    }

    public function testExecuteReportsAFailureWhenTheAnalyzerDoesNotExist(): void
    {
        $tester = new CommandTester(new InspectCommand(new InspectAction()));
        $status = $tester->execute([
            'target' => 'App\Domain\Invoice',
            '--type' => 'reflection',
            '--config' => __DIR__.'/absent.yaml',
        ]);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('type', $tester->getDisplay());
    }

    public function testExecuteBoundsTheTreeAtTheLevelAsked(): void
    {
        $root = (new DebugAnalyzer(seed: 42, depth: 3))->analyze('/generated')->nodes()[0]->id()->toString();
        $tester = new CommandTester(new InspectCommand(new InspectAction()));
        $tester->execute([
            'target' => $root,
            '--type' => 'debug',
            '--debug-seed' => '42',
            '--debug-depth' => '3',
            '--level' => '1',
            '--config' => __DIR__.'/absent.yaml',
        ]);

        self::assertStringNotContainsString('│   ', $tester->getDisplay());
    }

    public function testTheConfigurationFileDefaultsToTheOneInTheWorkingDirectory(): void
    {
        self::assertSame(getcwd().'/.peq.yaml', (new InspectCommand(new InspectAction()))->getDefinition()->getOption('config')->getDefault());
    }
}
