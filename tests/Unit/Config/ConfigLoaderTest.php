<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Config\ConfigException;
use App\Config\ConfigLoader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class ConfigLoaderTest extends TestCase
{
    #[Test]
    public function testLoadMergesConfigurationInOrder(): void
    {
        $reader1 = new StubConfigReader([
            'basePath' => '/path1',
            'direction' => 'uses',
            'type' => 'debug',
            'level' => 3,
        ]);

        $reader2 = new StubConfigReader([
            'direction' => 'used-by',
            'includes' => ['src/**'],
        ]);

        $reader3 = new StubConfigReader([
            'level' => 10,
            'excludes' => ['vendor/**'],
        ]);

        $loader = new ConfigLoader([$reader1, $reader2, $reader3]);
        $config = $loader->load();

        self::assertSame('/path1', $config->basePath);
        self::assertSame('used-by', $config->direction);
        self::assertSame(10, $config->level);
        self::assertSame(['src/**'], $config->includes);
        self::assertSame(['vendor/**'], $config->excludes);
    }

    #[Test]
    public function testLoadThrowsExceptionWhenValidationFails(): void
    {
        $reader = new StubConfigReader([
            'basePath' => '',
            'direction' => 'uses',
            'type' => 'debug',
        ]);

        $this->expectException(ConfigException::class);

        $loader = new ConfigLoader([$reader]);
        $loader->load();
    }
}
