<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Config\ConfigException;
use App\Config\YamlConfigLoader;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 */
#[CoversClass(YamlConfigLoader::class)]
#[UsesClass(\App\Config\RawConfig::class)]
#[Small]
final class YamlConfigLoaderTest extends TestCase
{
    /**
     * @throws ConfigException
     */
    public function testReadReportsTheSettingsTheFileHolds(): void
    {
        $root = vfsStream::setup('config');
        vfsStream::newFile('.peq.yaml')->at($root)->setContent(
            "basePath: /project\ndirection: uses\nlevel: 3\nexcludes:\n  - vendor\n",
        );

        $config = (new YamlConfigLoader(vfsStream::url('config/.peq.yaml')))->read();

        self::assertSame('/project', $config['basePath'] ?? null);
        self::assertSame('uses', $config['direction'] ?? null);
        self::assertSame(3, $config['level'] ?? null);
        self::assertSame(['vendor'], $config['excludes'] ?? null);
    }

    /**
     * @throws ConfigException
     */
    public function testReadReportsNothingWhenTheProjectHasNoConfigurationFile(): void
    {
        vfsStream::setup('config');

        self::assertSame([], (new YamlConfigLoader(vfsStream::url('config/absent.yaml')))->read());
    }

    /**
     * @throws ConfigException
     */
    public function testReadReportsNothingForAFileThatIsEmpty(): void
    {
        $root = vfsStream::setup('config');
        vfsStream::newFile('empty.yaml')->at($root)->setContent("\n");

        self::assertSame([], (new YamlConfigLoader(vfsStream::url('config/empty.yaml')))->read());
    }

    /**
     * @throws ConfigException
     */
    public function testReadRejectsAFileThatCannotBeParsed(): void
    {
        $root = vfsStream::setup('config');
        vfsStream::newFile('invalid.yaml')->at($root)->setContent("basePath: /path\n  invalid: indentation\n bad: yaml");

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('/Failed to parse YAML/');

        (new YamlConfigLoader(vfsStream::url('config/invalid.yaml')))->read();
    }

    /**
     * @throws ConfigException
     */
    public function testReadRejectsAFileHoldingSomethingOtherThanSettings(): void
    {
        $root = vfsStream::setup('config');
        vfsStream::newFile('scalar.yaml')->at($root)->setContent('just a string');

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('/Invalid configuration format/');

        (new YamlConfigLoader(vfsStream::url('config/scalar.yaml')))->read();
    }

    /**
     * @throws ConfigException
     */
    public function testReadRejectsAFileWhoseSettingsAreUnnamed(): void
    {
        $root = vfsStream::setup('config');
        vfsStream::newFile('list.yaml')->at($root)->setContent("- vendor\n- build\n");

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('/every setting must be named/');

        (new YamlConfigLoader(vfsStream::url('config/list.yaml')))->read();
    }

    /**
     * @throws ConfigException
     */
    public function testReadRejectsAFileItCannotOpen(): void
    {
        $root = vfsStream::setup('config');
        $file = vfsStream::newFile('unreadable.yaml', 0o000)->at($root)->setContent('basePath: /project');

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('/Failed to read configuration file/');

        (new YamlConfigLoader($file->url()))->read();
    }

    /**
     * @throws ConfigException
     */
    public function testContentsReadsWhatTheFileHolds(): void
    {
        $root = vfsStream::setup('config');
        vfsStream::newFile('.peq.yaml')->at($root)->setContent("basePath: /project\n");

        self::assertSame("basePath: /project\n", (new YamlConfigLoader(vfsStream::url('config/.peq.yaml')))->contents());
    }

    /**
     * @throws ConfigException
     */
    public function testContentsRejectsAFileItCannotOpen(): void
    {
        $root = vfsStream::setup('config');
        $file = vfsStream::newFile('locked.yaml', 0o000)->at($root)->setContent('basePath: /project');

        $this->expectException(ConfigException::class);

        (new YamlConfigLoader($file->url()))->contents();
    }

    /**
     * @throws ConfigException
     */
    public function testParseReadsWhatTheContentsDescribe(): void
    {
        self::assertSame(['basePath' => '/project'], (new YamlConfigLoader('/unused'))->parse("basePath: /project\n"));
    }

    /**
     * @throws ConfigException
     */
    public function testParseReadsNothingFromEmptyContents(): void
    {
        self::assertNull((new YamlConfigLoader('/unused'))->parse("\n"));
    }

    /**
     * @throws ConfigException
     */
    public function testParseRejectsContentsItCannotRead(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('/Failed to parse YAML/');

        (new YamlConfigLoader('/unused'))->parse("basePath: /path\n  invalid: indentation\n bad: yaml");
    }

    /**
     * @throws ConfigException
     */
    public function testSettingsReadsWhatTheContentsDescribedAsSettings(): void
    {
        $settings = (new YamlConfigLoader('/unused'))->settings(['basePath' => '/project', 'level' => 3]);

        self::assertSame(['basePath' => '/project', 'level' => 3], $settings);
    }

    /**
     * @throws ConfigException
     */
    public function testSettingsReadsNothingFromAFileThatDescribedNothing(): void
    {
        self::assertSame([], (new YamlConfigLoader('/unused'))->settings(null));
    }

    /**
     * @throws ConfigException
     */
    public function testSettingsRejectsContentsDescribingSomethingOtherThanSettings(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('/expected named settings/');

        (new YamlConfigLoader('/unused'))->settings('just a string');
    }

    /**
     * @throws ConfigException
     */
    public function testSettingsRejectsSettingsThatAreUnnamed(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('/every setting must be named/');

        (new YamlConfigLoader('/unused'))->settings(['unnamed']);
    }

    /**
     * @throws ConfigException
     */
    public function testFieldReadsASingleValueAsItWasWritten(): void
    {
        self::assertSame(3, (new YamlConfigLoader('/unused'))->field('level', 3));
    }

    /**
     * @throws ConfigException
     */
    public function testFieldReadsAListOfValuesAsAList(): void
    {
        self::assertSame(['vendor', 'build'], (new YamlConfigLoader('/unused'))->field('excludes', ['vendor', 'build']));
    }

    /**
     * @throws ConfigException
     */
    public function testFieldReadsNamedValuesAsAGroup(): void
    {
        self::assertSame(['depth' => 3], (new YamlConfigLoader('/unused'))->field('debug', ['depth' => 3]));
    }

    /**
     * @throws ConfigException
     */
    public function testFieldRejectsAValueNoSettingCanBeWrittenAs(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('/expected a setting/');

        (new YamlConfigLoader('/unused'))->field('level', new stdClass());
    }

    /**
     * @throws ConfigException
     */
    public function testGroupReadsTheSettingsAGroupHolds(): void
    {
        $group = (new YamlConfigLoader('/unused'))->group('debug', ['depth' => 3, 'seed' => 42]);

        self::assertSame(['depth' => 3, 'seed' => 42], $group);
    }

    /**
     * @throws ConfigException
     */
    public function testGroupRejectsASettingThatIsUnnamed(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('/every setting must be named/');

        (new YamlConfigLoader('/unused'))->group('debug', [3]);
    }

    /**
     * @throws ConfigException
     */
    public function testGroupRejectsAGroupNestedInsideAGroup(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('/expected a list of settings/');

        (new YamlConfigLoader('/unused'))->group('debug', ['nested' => ['depth' => 3]]);
    }

    /**
     * @throws ConfigException
     */
    public function testValuesReadsAListOfSettings(): void
    {
        self::assertSame(['vendor'], (new YamlConfigLoader('/unused'))->values('excludes', ['vendor']));
    }

    /**
     * @throws ConfigException
     */
    public function testValuesRejectsNamedEntriesWhereAListIsMeant(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('/expected a list of settings/');

        (new YamlConfigLoader('/unused'))->values('excludes', ['first' => 'vendor']);
    }

    /**
     * @throws ConfigException
     */
    public function testLeafReadsASingleValue(): void
    {
        self::assertSame('uses', (new YamlConfigLoader('/unused'))->leaf('direction', 'uses'));
    }

    /**
     * @throws ConfigException
     */
    public function testLeafRejectsAValueNoSettingCanBe(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('/expected a setting/');

        (new YamlConfigLoader('/unused'))->leaf('direction', new stdClass());
    }
}
