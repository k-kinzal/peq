<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Config\EnvConfigReader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class EnvConfigReaderTest extends TestCase
{
    #[Test]
    public function testReadReturnsConfigFromEnvironmentVariables(): void
    {
        putenv('PEQ_BASE_PATH=/env/path');
        putenv('PEQ_DIRECTION=used-by');
        putenv('PEQ_LEVEL=10');

        $reader = new EnvConfigReader('PEQ_');
        $config = $reader->read();

        self::assertSame('/env/path', $config['basePath']);
        self::assertSame('used-by', $config['direction']);
        self::assertSame('10', $config['level']);

        putenv('PEQ_BASE_PATH');
        putenv('PEQ_DIRECTION');
        putenv('PEQ_LEVEL');
    }

    #[Test]
    public function testReadIgnoresVariablesWithoutPrefix(): void
    {
        putenv('OTHER_VAR=value');
        putenv('PEQ_BASE_PATH=/test');

        $reader = new EnvConfigReader('PEQ_');
        $config = $reader->read();

        self::assertArrayNotHasKey('otherVar', $config);
        self::assertArrayHasKey('basePath', $config);

        putenv('OTHER_VAR');
        putenv('PEQ_BASE_PATH');
    }

    #[Test]
    public function testReadWithCustomPrefix(): void
    {
        putenv('CUSTOM_BASE_PATH=/custom/path');

        $reader = new EnvConfigReader('CUSTOM_');
        $config = $reader->read();

        self::assertSame('/custom/path', $config['basePath']);

        putenv('CUSTOM_BASE_PATH');
    }

    #[Test]
    public function testReadParsesDebugOptions(): void
    {
        putenv('PEQ_DEBUG_DEPTH=5');
        putenv('PEQ_DEBUG_SEED=123');

        $reader = new EnvConfigReader('PEQ_');
        $config = $reader->read();

        self::assertArrayHasKey('debug', $config);

        /** @var array{depth: string, seed: string} $debug */
        $debug = $config['debug'];
        self::assertSame('5', $debug['depth']);
        self::assertSame('123', $debug['seed']);

        putenv('PEQ_DEBUG_DEPTH');
        putenv('PEQ_DEBUG_SEED');
    }
}
