<?php

declare(strict_types=1);

namespace Tests\Unit\Action;

use App\Action\AnalyzerChoice;
use App\Analyzer\DebugAnalyzer\DebugAnalyzer;
use App\Analyzer\Graph\Direction;
use App\Analyzer\NativeAnalyzer\NativeAnalyzer;
use App\Analyzer\PhpStanAnalyzer\ContainerFactory;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use App\Config\AnalyzerKind;
use App\Config\Config;
use App\Config\ConfigException;
use App\Config\DebugAnalyzerConfig;
use App\Config\OutputFormat;
use App\Config\PhpVersion;
use App\Config\RawConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AnalyzerChoice::class)]
#[UsesClass(DebugAnalyzer::class)]
#[UsesClass(NativeAnalyzer::class)]
#[UsesClass(PhpStanAnalyzer::class)]
#[UsesClass(AnalyzerKind::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigException::class)]
#[UsesClass(DebugAnalyzerConfig::class)]
#[UsesClass(OutputFormat::class)]
#[UsesClass(RawConfig::class)]
#[UsesClass(Direction::class)]
#[UsesClass(PhpVersion::class)]
#[UsesClass(ContainerFactory::class)]
#[UsesClass(\App\Analyzer\PhaseCache::class)]
#[UsesClass(\App\Analyzer\CacheStorage::class)]
#[UsesClass(\App\Analyzer\ExecutionVersion::class)]
#[Small]
final class AnalyzerChoiceTest extends TestCase
{
    /**
     * @throws ConfigException
     */
    #[DataProvider('providerKindsAndTheAnalyzerTheyName')]
    public function testForConfigBuildsTheAnalyzerTheConfigurationNames(string $kind, string $expected): void
    {
        $config = Config::fromArray(['basePath' => '.', 'direction' => 'uses', 'type' => $kind]);

        self::assertSame($expected, AnalyzerChoice::forConfig($config)::class);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerKindsAndTheAnalyzerTheyName(): iterable
    {
        yield 'the one that reads the sources itself' => ['native', NativeAnalyzer::class];

        yield 'the one that asks PHPStan' => ['phpstan', PhpStanAnalyzer::class];

        yield 'the one that reads no sources at all' => ['debug', DebugAnalyzer::class];
    }

    /**
     * @throws ConfigException
     */
    public function testForConfigMakesTheSameChoiceForTheSameSettings(): void
    {
        $config = Config::fromArray(['basePath' => '.', 'direction' => 'uses', 'type' => 'native']);

        self::assertSame(AnalyzerChoice::forConfig($config)::class, AnalyzerChoice::forConfig($config)::class);
    }
}
