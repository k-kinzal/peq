<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Analyzer\Graph\Direction;
use App\Config\AnalyzerKind;
use App\Config\Config;
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
#[CoversClass(Config::class)]
#[UsesClass(DebugAnalyzerConfig::class)]
#[UsesClass(RawConfig::class)]
#[UsesClass(AnalyzerKind::class)]
#[Small]
final class ConfigTest extends TestCase
{
    /**
     * @throws ConfigException
     */
    public function testFromArrayReadsEverySettingWithTheTypeItNeedsToBe(): void
    {
        $config = Config::fromArray([
            'basePath' => '/project',
            'direction' => 'used-by',
            'level' => 3,
            'includes' => ['src'],
            'excludes' => ['vendor'],
            'phpVersion' => '7.4',
            'type' => 'phpstan',
            'debug' => ['depth' => 9, 'seed' => 42],
        ]);

        self::assertSame('/project', $config->basePath);
        self::assertSame(Direction::UsedBy, $config->direction);
        self::assertSame(3, $config->level);
        self::assertSame(['src'], $config->includes);
        self::assertSame(['vendor'], $config->excludes);
        self::assertSame(70400, $config->phpVersion?->id);
        self::assertSame(AnalyzerKind::PhpStan, $config->analyzer);
        self::assertSame(9, $config->debug->depth);
        self::assertSame(42, $config->debug->seed);
    }

    /**
     * @throws ConfigException
     */
    public function testFromArrayReadsANumberASourceCouldOnlyCarryAsText(): void
    {
        $config = Config::fromArray([
            'basePath' => '/project',
            'direction' => 'uses',
            'level' => '4',
            'type' => 'debug',
        ]);

        self::assertSame(4, $config->level);
    }

    /**
     * @throws ConfigException
     */
    public function testFromArrayLeavesTheReportUnboundedWhenNoLevelIsGiven(): void
    {
        $config = Config::fromArray(['basePath' => '.', 'direction' => 'uses', 'type' => 'debug']);

        self::assertNull($config->level);
    }

    /**
     * @throws ConfigException
     */
    public function testFromArrayGivesTheDebugAnalyzerItsSettingsEvenWhenNoneAreWritten(): void
    {
        $config = Config::fromArray(['basePath' => '.', 'direction' => 'uses', 'type' => 'debug']);

        self::assertSame(5, $config->debug->depth);
        self::assertNull($config->debug->seed);
    }

    /**
     * @throws ConfigException
     */
    public function testFromArrayRejectsADirectionThatNamesNoWayOfReadingTheGraph(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Invalid configuration "direction"');

        Config::fromArray(['basePath' => '.', 'direction' => 'sideways', 'type' => 'debug']);
    }

    /**
     * @throws ConfigException
     */
    public function testFromArrayRejectsAnAnalyzerThatDoesNotExist(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Invalid configuration "type"');

        Config::fromArray(['basePath' => '.', 'direction' => 'uses', 'type' => 'reflection']);
    }

    /**
     * @throws ConfigException
     */
    public function testFromArrayRejectsAMissingBasePath(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Invalid configuration "basePath"');

        Config::fromArray(['direction' => 'uses', 'type' => 'debug']);
    }

    /**
     * @throws ConfigException
     */
    public function testFromArrayRejectsALevelThatIsNotAPositiveNumberOfLevels(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Invalid configuration "level"');

        Config::fromArray(['basePath' => '.', 'direction' => 'uses', 'type' => 'debug', 'level' => 0]);
    }

    /**
     * @throws ConfigException
     */
    public function testFromArrayIgnoresSettingsItDoesNotKnow(): void
    {
        $config = Config::fromArray([
            'basePath' => '.',
            'direction' => 'uses',
            'type' => 'debug',
            'somethingElse' => 'ignored',
        ]);

        self::assertSame('.', $config->basePath);
    }

    public function testTheAnalyzerDefaultsToTheOneThatReadsRealSources(): void
    {
        self::assertSame(AnalyzerKind::PhpStan, (new Config('.', Direction::Uses))->analyzer);
    }

    public function testTheDebugSettingsExistWhicheverAnalyzerIsChosen(): void
    {
        $config = new Config('.', Direction::Uses, analyzer: AnalyzerKind::PhpStan);

        self::assertSame(5, $config->debug->depth);
    }

    public function testTheDebugSettingsAreKeptAsTheyWereGiven(): void
    {
        $settings = new DebugAnalyzerConfig(depth: 2, seed: 7);

        self::assertSame($settings, (new Config('.', Direction::Uses, debug: $settings))->debug);
    }

    public function testThePhpVersionOfTheAnalysedSourcesIsUnstatedUntilSomethingStatesIt(): void
    {
        self::assertNull((new Config('.', Direction::Uses))->phpVersion);
    }

    /**
     * @throws ConfigException
     */
    public function testAPhpVersionNoAnalyzerReadsIsRejectedLikeAnyOtherSetting(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('phpVersion');

        Config::fromArray([
            'basePath' => '/project',
            'direction' => 'uses',
            'type' => 'native',
            'phpVersion' => '5.5',
        ]);
    }

    /**
     * @throws ConfigException
     */
    public function testPhpVersionForKeepsAVersionTheChosenAnalyzerReads(): void
    {
        self::assertSame(
            70100,
            Config::phpVersionFor(new RawConfig(['phpVersion' => '7.1']), AnalyzerKind::PhpStan)?->id,
        );
    }

    /**
     * @throws ConfigException
     */
    public function testPhpVersionForKeepsNothingWhenNoSourceNamedAVersion(): void
    {
        self::assertNull(Config::phpVersionFor(new RawConfig([]), AnalyzerKind::PhpStan));
    }

    /**
     * @throws ConfigException
     */
    public function testPhpVersionForRejectsAVersionOlderThanTheChosenAnalyzerReads(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Invalid configuration "phpVersion": the phpstan analyzer reads sources written for PHP 7.1 and newer, got 5.6.');

        Config::phpVersionFor(new RawConfig(['phpVersion' => '5.6']), AnalyzerKind::PhpStan);
    }

    /**
     * @throws ConfigException
     */
    public function testPhpVersionForKeepsAVersionOnlyTheOtherAnalyzerReads(): void
    {
        self::assertSame(
            50600,
            Config::phpVersionFor(new RawConfig(['phpVersion' => '5.6']), AnalyzerKind::Native)?->id,
        );
    }

    /**
     * @throws ConfigException
     */
    public function testFromArrayRejectsAPhpVersionTheChosenAnalyzerCannotRead(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('phpVersion');

        Config::fromArray([
            'basePath' => '/project',
            'direction' => 'uses',
            'type' => 'phpstan',
            'phpVersion' => '5.6',
        ]);
    }

    /**
     * @throws ConfigException
     */
    public function testThePhpVersionKeepsThePatchReleaseItWasWrittenWith(): void
    {
        $config = Config::fromArray([
            'basePath' => '/project',
            'direction' => 'uses',
            'type' => 'phpstan',
            'phpVersion' => '8.3.2',
        ]);

        self::assertSame(80302, $config->phpVersion?->id);
    }
}
