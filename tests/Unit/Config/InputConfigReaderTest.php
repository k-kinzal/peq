<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Action\Inspect\InspectAction;
use App\Command\InspectCommand;
use App\Config\InputConfigReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Console\Input\ArrayInput;

/**
 * @internal
 */
#[CoversClass(InputConfigReader::class)]
#[UsesClass(InspectCommand::class)]
#[UsesClass(\App\Config\AnalyzerKind::class)]
#[Small]
final class InputConfigReaderTest extends TestCase
{
    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadReportsTheOptionsTheUserTyped(): void
    {
        $config = (new InputConfigReader(new ArrayInput([
            'target' => 'App\Domain\Invoice',
            '--direction' => 'used-by',
            '--level' => '5',
        ], (new InspectCommand(new InspectAction()))->getDefinition())))->read();

        self::assertSame('used-by', $config['direction'] ?? null);
        self::assertSame('5', $config['level'] ?? null);
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadReportsThePhpVersionTheUserTypedAsTyped(): void
    {
        $config = (new InputConfigReader(new ArrayInput([
            'target' => 'App\Domain\Invoice',
            '--php-version' => '7.4',
        ], (new InspectCommand(new InspectAction()))->getDefinition())))->read();

        self::assertSame('7.4', $config['phpVersion'] ?? null);
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadLeavesOutThePhpVersionTheUserDidNotType(): void
    {
        self::assertArrayNotHasKey(
            'phpVersion',
            (new InputConfigReader(new ArrayInput(['target' => 'App\Domain\Invoice'], (new InspectCommand(new InspectAction()))->getDefinition())))->read(),
        );
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadLeavesOutAnOptionTheUserDidNotType(): void
    {
        self::assertArrayNotHasKey('direction', (new InputConfigReader(new ArrayInput(['target' => 'App\Domain\Invoice'], (new InspectCommand(new InspectAction()))->getDefinition())))->read());
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadReportsThePathArgumentAsTheBasePath(): void
    {
        self::assertSame('/project', (new InputConfigReader(new ArrayInput(['target' => 'App\Domain\Invoice', 'path' => '/project'], (new InspectCommand(new InspectAction()))->getDefinition())))->read()['basePath'] ?? null);
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadLeavesOutTheSymbolBeingInspected(): void
    {
        self::assertArrayNotHasKey('target', (new InputConfigReader(new ArrayInput(['target' => 'App\Invoice'], (new InspectCommand(new InspectAction()))->getDefinition())))->read());
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadReportsIncludeAndExcludeUnderTheirPluralNames(): void
    {
        $config = (new InputConfigReader(new ArrayInput([
            'target' => 'App\Domain\Invoice',
            '--include' => ['src'],
            '--exclude' => ['vendor', 'build'],
        ], (new InspectCommand(new InspectAction()))->getDefinition())))->read();

        self::assertSame(['src'], $config['includes'] ?? null);
        self::assertSame(['vendor', 'build'], $config['excludes'] ?? null);
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadReportsDebugOptionsInsideTheDebugGroup(): void
    {
        $config = (new InputConfigReader(new ArrayInput([
            'target' => 'App\Domain\Invoice',
            '--debug-depth' => '9',
            '--debug-seed' => '42',
        ], (new InspectCommand(new InspectAction()))->getDefinition())))->read();

        self::assertSame(['depth' => '9', 'seed' => '42'], $config['debug'] ?? null);
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadLeavesTheDebugGroupOutWhenNoDebugOptionIsTyped(): void
    {
        self::assertArrayNotHasKey('debug', (new InputConfigReader(new ArrayInput(['target' => 'App\Domain\Invoice', '--type' => 'debug'], (new InspectCommand(new InspectAction()))->getDefinition())))->read());
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadTreatsReverseAsAskingForTheOppositeDirection(): void
    {
        self::assertSame('used-by', (new InputConfigReader(new ArrayInput(['target' => 'App\Domain\Invoice', '--reverse' => true], (new InspectCommand(new InspectAction()))->getDefinition())))->read()['direction'] ?? null);
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadReportsTheAnalyzerTheUserAskedFor(): void
    {
        self::assertSame('debug', (new InputConfigReader(new ArrayInput(['target' => 'App\Domain\Invoice', '--type' => 'debug'], (new InspectCommand(new InspectAction()))->getDefinition())))->read()['type'] ?? null);
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadReportsALevelExactlyAsItWasTypedRatherThanJudgingIt(): void
    {
        self::assertSame('nonsense', (new InputConfigReader(new ArrayInput(['target' => 'App\Domain\Invoice', '--level' => 'nonsense'], (new InspectCommand(new InspectAction()))->getDefinition())))->read()['level'] ?? null);
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testOptionReadsWhatTheUserTypedForOneOption(): void
    {
        $reader = new InputConfigReader(new ArrayInput(['target' => 'App\Domain\Invoice', '--direction' => 'used-by'], (new InspectCommand(new InspectAction()))->getDefinition()));

        self::assertSame('used-by', $reader->option('direction'));
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testOptionReadsARepeatedOptionAsAListOfValues(): void
    {
        $reader = new InputConfigReader(new ArrayInput(['target' => 'App\Domain\Invoice', '--exclude' => ['vendor', 'build']], (new InspectCommand(new InspectAction()))->getDefinition()));

        self::assertSame(['vendor', 'build'], $reader->option('exclude'));
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testOptionReadsAnOptionTheUserLeftOutAsNothing(): void
    {
        self::assertNull((new InputConfigReader(new ArrayInput(['target' => 'App\Domain\Invoice'], (new InspectCommand(new InspectAction()))->getDefinition())))->option('direction'));
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReportedReadsAValueAnOptionCanCarry(): void
    {
        self::assertSame('uses', (new InputConfigReader(new ArrayInput(['target' => 'App\Domain\Invoice'], (new InspectCommand(new InspectAction()))->getDefinition())))->reported('direction', 'uses'));
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReportedRejectsAValueNoSettingCanBe(): void
    {
        $this->expectException(\App\Config\ConfigException::class);
        $this->expectExceptionMessageMatches('/expected a setting/');

        (new InputConfigReader(new ArrayInput(['target' => 'App\Domain\Invoice'], (new InspectCommand(new InspectAction()))->getDefinition())))->reported('direction', new stdClass());
    }
}
