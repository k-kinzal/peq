<?php

declare(strict_types=1);

namespace Tests\App\Config;

use App\Config\AnalyzerType;
use App\Config\Config;
use App\Config\ConfigException;
use App\Config\DebugAnalyzerConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class ConfigTest extends TestCase
{
    #[Test]
    public function testConstruct(): void
    {
        $config = new Config(
            basePath: '/path/to/project',
            direction: 'uses',
            level: 5,
            includes: ['src/**/*.php'],
            excludes: ['vendor/**'],
            type: AnalyzerType::Debug,
            debug: new DebugAnalyzerConfig(depth: 3, seed: 123),
        );

        self::assertSame('/path/to/project', $config->basePath);
        self::assertSame('uses', $config->direction);
        self::assertSame(5, $config->level);
        self::assertSame(['src/**/*.php'], $config->includes);
        self::assertSame(['vendor/**'], $config->excludes);
        self::assertSame(AnalyzerType::Debug, $config->type);
        self::assertInstanceOf(DebugAnalyzerConfig::class, $config->debug);
        self::assertSame(3, $config->debug->depth);
        self::assertSame(123, $config->debug->seed);
    }

    #[Test]
    public function testConstructWithDefaults(): void
    {
        $config = new Config(
            basePath: '/path/to/project',
            direction: 'uses',
        );

        self::assertSame('/path/to/project', $config->basePath);
        self::assertSame('uses', $config->direction);
        self::assertNull($config->level);
        self::assertSame([], $config->includes);
        self::assertSame([], $config->excludes);
        self::assertSame(AnalyzerType::Debug, $config->type);
        self::assertInstanceOf(DebugAnalyzerConfig::class, $config->debug);
        self::assertSame(5, $config->debug->depth);
        self::assertNull($config->debug->seed);
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $expected
     */
    #[Test]
    #[DataProvider('providerFromArray')]
    public function testFromArray(array $input, array $expected): void
    {
        $config = Config::fromArray($input);

        self::assertSame($expected['basePath'], $config->basePath);
        self::assertSame($expected['direction'], $config->direction);
        self::assertSame($expected['level'], $config->level);
        self::assertSame($expected['includes'], $config->includes);
        self::assertSame($expected['excludes'], $config->excludes);
        self::assertSame($expected['type'], $config->type);
        self::assertNull($config->debug);
    }

    /**
     * @return array<string, array{array<string, mixed>, array<string, mixed>}>
     */
    public static function providerFromArray(): array
    {
        return [
            'minimal_required' => [
                [
                    'basePath' => '/tmp',
                    'direction' => 'uses',
                    'type' => 'debug',
                ],
                [
                    'basePath' => '/tmp',
                    'direction' => 'uses',
                    'level' => null,
                    'includes' => [],
                    'excludes' => [],
                    'type' => AnalyzerType::Debug,
                ],
            ],
            'with_level' => [
                [
                    'basePath' => '/tmp',
                    'direction' => 'used-by',
                    'type' => 'debug',
                    'level' => 3,
                ],
                [
                    'basePath' => '/tmp',
                    'direction' => 'used-by',
                    'level' => 3,
                    'includes' => [],
                    'excludes' => [],
                    'type' => AnalyzerType::Debug,
                ],
            ],
            'with_includes' => [
                [
                    'basePath' => '/project',
                    'direction' => 'uses',
                    'type' => 'debug',
                    'includes' => ['src/**'],
                ],
                [
                    'basePath' => '/project',
                    'direction' => 'uses',
                    'level' => null,
                    'includes' => ['src/**'],
                    'excludes' => [],
                    'type' => AnalyzerType::Debug,
                ],
            ],
            'with_excludes' => [
                [
                    'basePath' => '/project',
                    'direction' => 'uses',
                    'type' => 'debug',
                    'excludes' => ['vendor/**'],
                ],
                [
                    'basePath' => '/project',
                    'direction' => 'uses',
                    'level' => null,
                    'includes' => [],
                    'excludes' => ['vendor/**'],
                    'type' => AnalyzerType::Debug,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $expected
     */
    #[Test]
    #[DataProvider('providerFromArrayWithDebugConfig')]
    public function testFromArrayWithDebugConfig(array $input, array $expected): void
    {
        $config = Config::fromArray($input);

        self::assertSame($expected['basePath'], $config->basePath);
        self::assertSame($expected['direction'], $config->direction);
        self::assertSame($expected['level'], $config->level);
        self::assertSame($expected['includes'], $config->includes);
        self::assertSame($expected['excludes'], $config->excludes);
        self::assertSame($expected['type'], $config->type);
        self::assertNotNull($config->debug);
        assert(is_array($expected['debug']));
        self::assertSame($expected['debug']['depth'], $config->debug->depth);
        self::assertSame($expected['debug']['seed'], $config->debug->seed);
    }

    /**
     * @return array<string, array{array<string, mixed>, array<string, mixed>}>
     */
    public static function providerFromArrayWithDebugConfig(): array
    {
        return [
            'full_options' => [
                [
                    'basePath' => '/path/to/project',
                    'direction' => 'used-by',
                    'level' => 5,
                    'includes' => ['src/**/*.php', 'lib/**/*.php'],
                    'excludes' => ['vendor/**', 'tests/**'],
                    'type' => 'debug',
                    'debug' => ['depth' => 3, 'seed' => 12345],
                ],
                [
                    'basePath' => '/path/to/project',
                    'direction' => 'used-by',
                    'level' => 5,
                    'includes' => ['src/**/*.php', 'lib/**/*.php'],
                    'excludes' => ['vendor/**', 'tests/**'],
                    'type' => AnalyzerType::Debug,
                    'debug' => [
                        'depth' => 3,
                        'seed' => 12345,
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    #[Test]
    #[DataProvider('providerFromArrayWithInvalidData')]
    public function testFromArrayWithInvalidData(array $input): void
    {
        $this->expectException(ConfigException::class);
        Config::fromArray($input);
    }

    /**
     * @return array<string, array<array<string, mixed>>>
     */
    public static function providerFromArrayWithInvalidData(): array
    {
        return [
            'missing_basePath' => [[
                'direction' => 'uses',
                'type' => 'debug',
            ]],
            'basePath_null' => [[
                'basePath' => null,
                'direction' => 'uses',
                'type' => 'debug',
            ]],
            'basePath_not_string' => [[
                'basePath' => 123,
                'direction' => 'uses',
                'type' => 'debug',
            ]],
            'empty_basePath' => [[
                'basePath' => '',
                'direction' => 'uses',
                'type' => 'debug',
            ]],
            'missing_direction' => [[
                'basePath' => '/tmp',
                'type' => 'debug',
            ]],
            'direction_null' => [[
                'basePath' => '/tmp',
                'direction' => null,
                'type' => 'debug',
            ]],
            'direction_not_string' => [[
                'basePath' => '/tmp',
                'direction' => 123,
                'type' => 'debug',
            ]],
            'invalid_direction' => [[
                'basePath' => '/path/to/project',
                'direction' => 'invalid-direction',
                'type' => 'debug',
            ]],
            'missing_type' => [[
                'basePath' => '/tmp',
                'direction' => 'uses',
            ]],
            'type_null' => [[
                'basePath' => '/tmp',
                'direction' => 'uses',
                'type' => null,
            ]],
            'type_not_string' => [[
                'basePath' => '/tmp',
                'direction' => 'uses',
                'type' => 123,
            ]],
            'invalid_type' => [[
                'basePath' => '/tmp',
                'direction' => 'uses',
                'type' => 'invalid',
            ]],
            'level_not_int' => [[
                'basePath' => '/tmp',
                'direction' => 'uses',
                'type' => 'debug',
                'level' => 'five',
            ]],
            'level_zero' => [[
                'basePath' => '/path/to/project',
                'direction' => 'uses',
                'type' => 'debug',
                'level' => 0,
            ]],
            'level_negative' => [[
                'basePath' => '/path/to/project',
                'direction' => 'uses',
                'type' => 'debug',
                'level' => -1,
            ]],
            'includes_not_array' => [[
                'basePath' => '/tmp',
                'direction' => 'uses',
                'type' => 'debug',
                'includes' => 'not-an-array',
            ]],
            'includes_with_non_string' => [[
                'basePath' => '/path/to/project',
                'direction' => 'uses',
                'type' => 'debug',
                'includes' => ['valid', 123, 'also-valid'],
            ]],
            'includes_with_empty_string' => [[
                'basePath' => '/path/to/project',
                'direction' => 'uses',
                'type' => 'debug',
                'includes' => ['valid', '', 'also-valid'],
            ]],
            'excludes_not_array' => [[
                'basePath' => '/tmp',
                'direction' => 'uses',
                'type' => 'debug',
                'excludes' => 'not-an-array',
            ]],
            'excludes_with_non_string' => [[
                'basePath' => '/path/to/project',
                'direction' => 'uses',
                'type' => 'debug',
                'excludes' => ['valid', 123, 'also-valid'],
            ]],
            'excludes_with_empty_string' => [[
                'basePath' => '/path/to/project',
                'direction' => 'uses',
                'type' => 'debug',
                'excludes' => ['valid', '', 'also-valid'],
            ]],
            'debug_not_array' => [[
                'basePath' => '/tmp',
                'direction' => 'uses',
                'type' => 'debug',
                'debug' => 'not-an-array',
            ]],
        ];
    }
}
