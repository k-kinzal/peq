<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Config\InputConfigReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Config\ConsoleInput;

/**
 * @internal
 */
#[CoversClass(InputConfigReader::class)]
#[Small]
final class InputConfigReaderTest extends TestCase
{
    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadReportsTheOptionsTheUserTyped(): void
    {
        $config = (new InputConfigReader(ConsoleInput::of([
            '--direction' => 'used-by',
            '--level' => '5',
        ])))->read();

        self::assertSame('used-by', $config['direction'] ?? null);
        self::assertSame('5', $config['level'] ?? null);
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadLeavesOutAnOptionTheUserDidNotType(): void
    {
        self::assertArrayNotHasKey('direction', (new InputConfigReader(ConsoleInput::of([])))->read());
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadReportsThePathArgumentAsTheBasePath(): void
    {
        self::assertSame('/project', (new InputConfigReader(ConsoleInput::of(['path' => '/project'])))->read()['basePath'] ?? null);
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadLeavesOutTheSymbolBeingInspected(): void
    {
        self::assertArrayNotHasKey('target', (new InputConfigReader(ConsoleInput::of(['target' => 'App\Invoice'])))->read());
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadReportsIncludeAndExcludeUnderTheirPluralNames(): void
    {
        $config = (new InputConfigReader(ConsoleInput::of([
            '--include' => ['src'],
            '--exclude' => ['vendor', 'build'],
        ])))->read();

        self::assertSame(['src'], $config['includes'] ?? null);
        self::assertSame(['vendor', 'build'], $config['excludes'] ?? null);
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadReportsDebugOptionsInsideTheDebugGroup(): void
    {
        $config = (new InputConfigReader(ConsoleInput::of([
            '--debug-depth' => '9',
            '--debug-seed' => '42',
        ])))->read();

        self::assertSame(['depth' => '9', 'seed' => '42'], $config['debug'] ?? null);
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadLeavesTheDebugGroupOutWhenNoDebugOptionIsTyped(): void
    {
        self::assertArrayNotHasKey('debug', (new InputConfigReader(ConsoleInput::of(['--type' => 'debug'])))->read());
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadTreatsReverseAsAskingForTheOppositeDirection(): void
    {
        self::assertSame('used-by', (new InputConfigReader(ConsoleInput::of(['--reverse' => true])))->read()['direction'] ?? null);
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadReportsTheAnalyzerTheUserAskedFor(): void
    {
        self::assertSame('debug', (new InputConfigReader(ConsoleInput::of(['--type' => 'debug'])))->read()['type'] ?? null);
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadReportsALevelExactlyAsItWasTypedRatherThanJudgingIt(): void
    {
        self::assertSame('nonsense', (new InputConfigReader(ConsoleInput::of(['--level' => 'nonsense'])))->read()['level'] ?? null);
    }
}
