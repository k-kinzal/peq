<?php

declare(strict_types=1);

namespace Tests\App\Config;

use App\Config\ConfigException;
use App\Config\DebugAnalyzerConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class DebugAnalyzerConfigTest extends TestCase
{
    #[Test]
    public function testConstruct(): void
    {
        $config = new DebugAnalyzerConfig(depth: 10, seed: 12345);

        self::assertSame(10, $config->depth);
        self::assertSame(12345, $config->seed);
    }

    #[Test]
    public function testConstructWithDefaults(): void
    {
        $config = new DebugAnalyzerConfig();

        self::assertSame(5, $config->depth);
        self::assertNull($config->seed);
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $expected
     */
    #[Test]
    #[DataProvider('providerFromArray')]
    public function testFromArray(array $input, array $expected): void
    {
        $config = DebugAnalyzerConfig::fromArray($input);

        self::assertSame($expected['depth'], $config->depth);
        self::assertSame($expected['seed'], $config->seed);
    }

    /**
     * @return array<string, array{array<string, mixed>, array<string, mixed>}>
     */
    public static function providerFromArray(): array
    {
        return [
            'minimal_required' => [
                [
                    'depth' => 5,
                ],
                [
                    'depth' => 5,
                    'seed' => null,
                ],
            ],
            'full_options' => [
                [
                    'depth' => 10,
                    'seed' => 12345,
                ],
                [
                    'depth' => 10,
                    'seed' => 12345,
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
        DebugAnalyzerConfig::fromArray($input);
    }

    /**
     * @return array<string, array<array<string, mixed>>>
     */
    public static function providerFromArrayWithInvalidData(): array
    {
        return [
            'missing_depth' => [[
                'seed' => 123,
            ]],
            'invalid_depth_type' => [[
                'depth' => 'string',
            ]],
            'invalid_depth_value' => [[
                'depth' => 0,
            ]],
            'invalid_seed_type' => [[
                'depth' => 5,
                'seed' => 'string',
            ]],
        ];
    }
}
