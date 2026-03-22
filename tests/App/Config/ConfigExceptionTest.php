<?php

declare(strict_types=1);

namespace Tests\App\Config;

use App\Config\ConfigException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class ConfigExceptionTest extends TestCase
{
    #[Test]
    public function testConstructWithAllParameters(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new ConfigException('Test message', 123, $previous);

        self::assertSame('Test message', $exception->getMessage());
        self::assertSame(123, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
    }
}
