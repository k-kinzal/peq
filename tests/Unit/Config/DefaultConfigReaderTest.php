<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Config\DefaultConfigReader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class DefaultConfigReaderTest extends TestCase
{
    #[Test]
    public function testReadReturnsDefaultConfiguration(): void
    {
        $reader = new DefaultConfigReader();
        $config = $reader->read();

        self::assertSame('.', $config['basePath']);
        self::assertSame('uses', $config['direction']);
        self::assertNull($config['level']);
        self::assertSame([], $config['includes']);
        self::assertSame([], $config['excludes']);
    }
}
