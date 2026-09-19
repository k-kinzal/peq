<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Config\Config;
use App\Config\DefaultConfigReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DefaultConfigReader::class)]
#[UsesClass(Config::class)]
#[UsesClass(\App\Config\DebugAnalyzerConfig::class)]
#[UsesClass(\App\Config\RawConfig::class)]
#[UsesClass(\App\Config\AnalyzerKind::class)]
#[Small]
final class DefaultConfigReaderTest extends TestCase
{
    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadReportsABaselineEveryOtherSourceCanOverlay(): void
    {
        $defaults = (new DefaultConfigReader())->read();

        self::assertSame('.', $defaults['basePath'] ?? null);
        self::assertSame('uses', $defaults['direction'] ?? null);
        self::assertSame('phpstan', $defaults['type'] ?? null);
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadReportsABaselineThatIsAlreadyAValidConfiguration(): void
    {
        self::assertSame('.', Config::fromArray((new DefaultConfigReader())->read())->basePath);
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadNamesThePhpVersionTheAnalysedSourcesAreReadAs(): void
    {
        self::assertSame(
            \App\Config\PhpVersion::host()->toString(),
            (new DefaultConfigReader())->read()['phpVersion'] ?? null,
        );
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadNamesAPhpVersionTheAnalysisCanBeAskedFor(): void
    {
        self::assertNotNull(Config::fromArray((new DefaultConfigReader())->read())->phpVersion);
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadLeavesTheReportUnbounded(): void
    {
        $defaults = (new DefaultConfigReader())->read();

        self::assertArrayHasKey('level', $defaults);
        self::assertNull($defaults['level']);
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadAnalysesEverythingUntilToldOtherwise(): void
    {
        $defaults = (new DefaultConfigReader())->read();

        self::assertSame([], $defaults['includes'] ?? null);
        self::assertSame([], $defaults['excludes'] ?? null);
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadReportsTheSameBaselineEveryTime(): void
    {
        self::assertSame((new DefaultConfigReader())->read(), (new DefaultConfigReader())->read());
    }
}
