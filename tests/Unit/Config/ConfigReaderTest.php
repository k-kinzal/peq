<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Config\ConfigReader;
use App\Config\DefaultConfigReader;
use App\Config\EnvConfigReader;
use App\Config\YamlConfigLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DefaultConfigReader::class)]
#[CoversClass(EnvConfigReader::class)]
#[CoversClass(YamlConfigLoader::class)]
#[UsesClass(\App\Config\AnalyzerKind::class)]
#[Small]
final class ConfigReaderTest extends TestCase
{
    /**
     * @throws \App\Config\ConfigException
     */
    #[DataProvider('providerEverySourceOfConfiguration')]
    public function testReadReportsSettingsUnderTheNamesTheApplicationUses(ConfigReader $reader): void
    {
        $names = array_keys($reader->read());

        self::assertSame($names, array_values(array_filter($names, 'is_string')));
    }

    /**
     * @throws \App\Config\ConfigException
     */
    #[DataProvider('providerEverySourceOfConfiguration')]
    public function testReadReportsNothingRatherThanFailingWhenASourceIsAbsent(ConfigReader $reader): void
    {
        self::assertSame($reader->read(), $reader->read());
    }

    /**
     * @return iterable<string, array{ConfigReader}>
     */
    public static function providerEverySourceOfConfiguration(): iterable
    {
        yield 'the defaults' => [new DefaultConfigReader()];

        yield 'the environment' => [new EnvConfigReader('PEQ_TEST_ABSENT_')];

        yield 'a configuration file that does not exist' => [new YamlConfigLoader('/nonexistent/.peq.yaml')];
    }

    /**
     * @throws \App\Config\ConfigException
     */
    #[DataProvider('providerSourcesThatKnowNothing')]
    public function testReadReportsNothingFromASourceThatKnowsNothing(ConfigReader $reader): void
    {
        self::assertSame([], $reader->read());
    }

    /**
     * @return iterable<string, array{ConfigReader}>
     */
    public static function providerSourcesThatKnowNothing(): iterable
    {
        yield 'an environment with nothing set under the prefix' => [new EnvConfigReader('PEQ_TEST_ABSENT_')];

        yield 'a configuration file that does not exist' => [new YamlConfigLoader('/nonexistent/.peq.yaml')];
    }
}
