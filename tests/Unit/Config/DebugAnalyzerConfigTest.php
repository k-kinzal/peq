<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Config\ConfigException;
use App\Config\DebugAnalyzerConfig;
use App\Config\RawConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DebugAnalyzerConfig::class)]
#[UsesClass(RawConfig::class)]
#[Small]
final class DebugAnalyzerConfigTest extends TestCase
{
    public function testTheDepthDefaultsToAGraphDeepEnoughToRead(): void
    {
        self::assertSame(5, (new DebugAnalyzerConfig())->depth);
    }

    public function testAGeneratedGraphIsFreshUnlessASeedIsGiven(): void
    {
        self::assertNull((new DebugAnalyzerConfig())->seed);
    }

    /**
     * @throws ConfigException
     */
    public function testFromRawReadsBothSettings(): void
    {
        $settings = DebugAnalyzerConfig::fromRaw(new RawConfig(['depth' => 9, 'seed' => 42]));

        self::assertSame(9, $settings->depth);
        self::assertSame(42, $settings->seed);
    }

    /**
     * @throws ConfigException
     */
    public function testFromRawReadsSettingsASourceCouldOnlyCarryAsText(): void
    {
        $settings = DebugAnalyzerConfig::fromRaw(new RawConfig(['depth' => '9', 'seed' => '42']));

        self::assertSame(9, $settings->depth);
        self::assertSame(42, $settings->seed);
    }

    /**
     * @throws ConfigException
     */
    public function testFromRawFallsBackToTheDefaultsForSettingsLeftOut(): void
    {
        $settings = DebugAnalyzerConfig::fromRaw(new RawConfig([]));

        self::assertSame(5, $settings->depth);
        self::assertNull($settings->seed);
    }

    /**
     * @throws ConfigException
     */
    public function testFromRawAcceptsASeedOfZero(): void
    {
        self::assertSame(0, DebugAnalyzerConfig::fromRaw(new RawConfig(['seed' => 0]))->seed);
    }

    /**
     * @throws ConfigException
     */
    public function testFromRawRejectsADepthThatIsNotAPositiveNumberOfLevels(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Invalid configuration "depth"');

        DebugAnalyzerConfig::fromRaw(new RawConfig(['depth' => 0]));
    }
}
