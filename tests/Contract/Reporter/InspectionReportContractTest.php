<?php

declare(strict_types=1);

namespace Tests\Contract\Reporter;

use App\Action\Inspect\InspectAction;
use App\Command\InspectCommand;
use App\Config\OutputFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(InspectCommand::class)]
#[CoversClass(InspectAction::class)]
#[CoversClass(\App\Reporter\TreeReporter\TreeCursor::class)]
#[CoversClass(\App\Reporter\TreeReporter\LineRenderer::class)]
#[Medium]
final class InspectionReportContractTest extends TestCase
{
    #[DataProvider('providerFormats')]
    public function testExecuteShowsTheSameInterfaceCallInEveryFormat(string $format): void
    {
        $file = tempnam(sys_get_temp_dir(), 'peq-filter-');
        self::assertNotFalse($file);
        file_put_contents($file, <<<'PHP'
            <?php
            interface Port { public function run(): void; public function unused(): void; }
            class Service implements Port { public function run(): void {} public function unused(): void {} }
            class Controller { public function __construct(private Port $port) {} public function action(): void { $this->port->run(); } }
            PHP);
        $tester = new CommandTester(new InspectCommand(new InspectAction()));

        $status = $tester->execute(['target' => 'Controller::action', 'path' => $file, '--type' => 'native', '--output' => $format, '--config' => __DIR__.'/absent.yaml']);
        unlink($file);
        $output = $tester->getDisplay();

        self::assertSame(0, $status);
        self::assertStringContainsString('Port::run', $output);
        self::assertStringContainsString('Service::run', $output);
        self::assertStringNotContainsString('unused', $output);
        self::assertStringNotContainsString('Controller::port', $output);
    }

    #[DataProvider('providerFormats')]
    public function testExecuteFindsCallersThroughPossibleImplementationsInEveryFormat(string $format): void
    {
        $file = tempnam(sys_get_temp_dir(), 'peq-filter-');
        self::assertNotFalse($file);
        file_put_contents($file, <<<'PHP'
            <?php
            interface Port { public function run(): void; }
            class Service implements Port { public function run(): void {} }
            class Controller { public function action(Port $port): void { $port->run(); } }
            PHP);
        $tester = new CommandTester(new InspectCommand(new InspectAction()));

        $status = $tester->execute(['target' => 'Service::run', 'path' => $file, '--type' => 'native', '--output' => $format, '--reverse' => true, '--config' => __DIR__.'/absent.yaml']);

        unlink($file);
        self::assertSame(0, $status);
        self::assertStringContainsString('Controller::action', $tester->getDisplay());
    }

    #[DataProvider('providerFormats')]
    public function testExecuteAggregatesClassDependenciesInEveryFormat(string $format): void
    {
        $file = tempnam(sys_get_temp_dir(), 'peq-filter-');
        self::assertNotFalse($file);
        file_put_contents($file, <<<'PHP'
            <?php
            interface Port { public function run(): void; }
            class Service implements Port { public function run(): void {} }
            class Controller { public function action(Port $port): void { $port->run(); } }
            PHP);
        $tester = new CommandTester(new InspectCommand(new InspectAction()));

        $status = $tester->execute(['target' => 'Controller', 'path' => $file, '--type' => 'native', '--output' => $format, '--config' => __DIR__.'/absent.yaml']);
        unlink($file);
        $output = $tester->getDisplay();

        self::assertSame(0, $status);
        self::assertStringContainsString('Service', $output);
        self::assertStringContainsString('Port', $output);
        self::assertStringNotContainsString('::run', $output);
        self::assertStringNotContainsString('::action', $output);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testExecuteLayersTheFilterFromEnvironmentThenYamlThenCli(): void
    {
        putenv('PEQ_FILTER=all');
        $config = tempnam(sys_get_temp_dir(), 'peq-config-');
        self::assertNotFalse($config);
        file_put_contents($config, "filter: calls\n");
        $tester = new CommandTester(new InspectCommand(new InspectAction()));
        $arguments = ['target' => 'Tests\Fixture\Source\Dip\Controller::action', 'path' => dirname(__DIR__, 2).'/Fixture/Source/Dip.php', '--type' => 'native', '--config' => $config];

        $tester->execute($arguments);
        $fromYaml = $tester->getDisplay();
        $tester->execute($arguments + ['--filter' => 'all']);
        $fromCli = $tester->getDisplay();
        unlink($config);

        self::assertStringNotContainsString('Controller::port', $fromYaml);
        self::assertStringContainsString('Controller::port', $fromCli);
    }

    #[DataProvider('providerFormats')]
    public function testExecuteAppliesTheDepthBoundAfterChoosingTheCallFilter(string $format): void
    {
        $file = tempnam(sys_get_temp_dir(), 'peq-filter-');
        self::assertNotFalse($file);
        file_put_contents($file, <<<'PHP'
            <?php
            interface Port { public function run(): void; }
            class Service implements Port { public function run(): void { $this->again(); } public function again(): void { $this->run(); } }
            class Controller { public function action(Port $port): void { $port->run(); } }
            PHP);
        $tester = new CommandTester(new InspectCommand(new InspectAction()));

        $status = $tester->execute(['target' => 'Controller::action', 'path' => $file, '--type' => 'native', '--output' => $format, '--level' => '1', '--config' => __DIR__.'/absent.yaml']);
        unlink($file);
        $output = $tester->getDisplay();

        self::assertSame(0, $status);
        self::assertStringContainsString('Service::run', $output);
        self::assertStringNotContainsString('Service::again', $output);
    }

    /**
     * @return list<array{string}>
     */
    public static function providerFormats(): array
    {
        return array_map(static fn (OutputFormat $format): array => [$format->value], OutputFormat::cases());
    }

    #[DataProvider('providerHumanFormats')]
    public function testExecuteMarksOnlyInferredBranchesInHumanOutput(string $format): void
    {
        $file = tempnam(sys_get_temp_dir(), 'peq-filter-');
        self::assertNotFalse($file);
        file_put_contents($file, '<?php interface Port { function run(); } class Service implements Port { function run() {} } class Controller { function action(Port $port) { $port->run(); } }');
        $tester = new CommandTester(new InspectCommand(new InspectAction()));

        $status = $tester->execute(['target' => 'Controller::action', 'path' => $file, '--type' => 'native', '--output' => $format, '--config' => __DIR__.'/absent.yaml']);
        unlink($file);

        self::assertSame(0, $status);
        self::assertStringContainsString('Service::run (possible)', $tester->getDisplay());
        self::assertStringNotContainsString('Port::run (possible)', $tester->getDisplay());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerHumanFormats(): array
    {
        return ['tree' => ['tree'], 'table' => ['table']];
    }
}
