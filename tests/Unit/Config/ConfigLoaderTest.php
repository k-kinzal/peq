<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Analyzer\Graph\Direction;
use App\Config\ConfigException;
use App\Config\ConfigLoader;
use App\Config\ConfigReader;
use App\Config\PhpVersion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Config\StubConfigReader;

/**
 * @internal
 *
 * @phpstan-import-type ConfigField from ConfigReader
 * @phpstan-import-type ConfigGroup from ConfigReader
 * @phpstan-import-type ConfigFields from ConfigReader
 */
#[CoversClass(ConfigLoader::class)]
#[UsesClass(\App\Config\Config::class)]
#[UsesClass(\App\Config\DebugAnalyzerConfig::class)]
#[UsesClass(\App\Config\RawConfig::class)]
#[UsesClass(\App\Config\AnalyzerKind::class)]
#[UsesClass(PhpVersion::class)]
#[Small]
final class ConfigLoaderTest extends TestCase
{
    /**
     * @throws ConfigException
     */
    public function testLoadLetsALaterSourceOverruleAnEarlierOne(): void
    {
        $config = (new ConfigLoader([
            new StubConfigReader(['basePath' => '/default', 'direction' => 'uses', 'type' => 'debug']),
            new StubConfigReader(['basePath' => '/project']),
        ]))->load();

        self::assertSame('/project', $config->basePath);
    }

    /**
     * @throws ConfigException
     */
    public function testLoadKeepsWhatALaterSourceSaysNothingAbout(): void
    {
        $config = (new ConfigLoader([
            new StubConfigReader(['basePath' => '/default', 'direction' => 'used-by', 'type' => 'debug']),
            new StubConfigReader(['basePath' => '/project']),
        ]))->load();

        self::assertSame(Direction::UsedBy, $config->direction);
    }

    /**
     * @throws ConfigException
     */
    public function testLoadCombinesAGroupSettingBySetting(): void
    {
        $config = (new ConfigLoader([
            new StubConfigReader(['basePath' => '.', 'direction' => 'uses', 'type' => 'debug', 'debug' => ['depth' => 9, 'seed' => 1]]),
            new StubConfigReader(['debug' => ['seed' => 42]]),
        ]))->load();

        self::assertSame(9, $config->debug->depth);
        self::assertSame(42, $config->debug->seed);
    }

    /**
     * @throws ConfigException
     */
    public function testLoadReplacesAListRatherThanAddingToIt(): void
    {
        $config = (new ConfigLoader([
            new StubConfigReader(['basePath' => '.', 'direction' => 'uses', 'type' => 'debug', 'excludes' => ['vendor', 'build']]),
            new StubConfigReader(['excludes' => ['vendor']]),
        ]))->load();

        self::assertSame(['vendor'], $config->excludes);
    }

    /**
     * @throws ConfigException
     */
    public function testLoadRejectsMergedDataThatIsNotAConfiguration(): void
    {
        $this->expectException(ConfigException::class);

        (new ConfigLoader([new StubConfigReader(['direction' => 'uses'])]))->load();
    }

    /**
     * @throws ConfigException
     */
    public function testLoadWithNoSourcesRejectsTheEmptyConfiguration(): void
    {
        $this->expectException(ConfigException::class);

        (new ConfigLoader([]))->load();
    }

    /**
     * @param ConfigFields $base
     * @param ConfigFields $overlay
     * @param ConfigFields $expected
     */
    #[DataProvider('providerOverlays')]
    public function testOverlayCombinesWhatTwoSourcesReported(array $base, array $overlay, array $expected): void
    {
        self::assertSame($expected, ConfigLoader::overlay($base, $overlay));
    }

    /**
     * @return iterable<string, array{ConfigFields, ConfigFields, ConfigFields}>
     */
    public static function providerOverlays(): iterable
    {
        yield 'a later value wins' => [['level' => 1], ['level' => 2], ['level' => 2]];

        yield 'an unmentioned setting is kept' => [['level' => 1], ['basePath' => '.'], ['level' => 1, 'basePath' => '.']];

        yield 'groups are combined setting by setting' => [
            ['debug' => ['depth' => 9, 'seed' => 1]],
            ['debug' => ['seed' => 42]],
            ['debug' => ['depth' => 9, 'seed' => 42]],
        ];

        yield 'a list replaces a list' => [['excludes' => ['a', 'b']], ['excludes' => ['c']], ['excludes' => ['c']]];

        yield 'a single value replaces a group' => [['debug' => ['depth' => 9]], ['debug' => 3], ['debug' => 3]];

        yield 'nothing overlaid changes nothing' => [['level' => 1], [], ['level' => 1]];

        yield 'a group followed by a single setting' => [['debug' => ['depth' => 1], 'level' => 1], ['debug' => ['seed' => 2], 'level' => 3], ['debug' => ['depth' => 1, 'seed' => 2], 'level' => 3]];
    }

    /**
     * @param null|ConfigField $value
     */
    #[DataProvider('providerNamedGroups')]
    public function testIsNamedGroupRecognisesAGroupOfNamedSettings(array|bool|float|int|string|null $value, bool $expected): void
    {
        self::assertSame($expected, ConfigLoader::isNamedGroup($value));
    }

    /**
     * @return iterable<string, array{null|ConfigField, bool}>
     */
    public static function providerNamedGroups(): iterable
    {
        yield 'named settings' => [['depth' => 9], true];

        yield 'a list' => [['a', 'b'], false];

        yield 'an empty array' => [[], false];

        yield 'a partly named array' => [['depth' => 9, 0 => 'a'], false];

        yield 'a single value' => ['debug', false];

        yield 'nothing' => [null, false];
    }

    /**
     * @param ConfigGroup $base
     * @param ConfigGroup $overlay
     * @param ConfigGroup $expected
     */
    #[DataProvider('providerGroupOverlays')]
    public function testOverlayGroupCombinesTwoGroupsSettingBySetting(array $base, array $overlay, array $expected): void
    {
        self::assertSame($expected, ConfigLoader::overlayGroup($base, $overlay));
    }

    /**
     * @return iterable<string, array{ConfigGroup, ConfigGroup, ConfigGroup}>
     */
    public static function providerGroupOverlays(): iterable
    {
        yield 'a later setting wins' => [['seed' => 1], ['seed' => 42], ['seed' => 42]];

        yield 'an unmentioned setting is kept' => [['depth' => 9], ['seed' => 42], ['depth' => 9, 'seed' => 42]];

        yield 'nothing overlaid changes nothing' => [['depth' => 9], [], ['depth' => 9]];
    }
}
