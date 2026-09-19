<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Config\ConfigException;
use App\Config\RawConfig;
use Exception;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
#[CoversClass(ConfigException::class)]
#[UsesClass(RawConfig::class)]
#[Small]
final class ConfigExceptionTest extends TestCase
{
    /**
     * @throws ConfigException
     */
    public function testAConfigurationErrorNamesTheSettingItIsAbout(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Invalid configuration "level"');

        (new RawConfig(['level' => 'deep']))->optionalInt('level');
    }

    public function testAConfigurationErrorMustBeHandledRatherThanEscaping(): void
    {
        $families = class_parents(new ConfigException('unusable'));

        self::assertNotContains(RuntimeException::class, $families);
        self::assertNotContains(LogicException::class, $families);
    }

    public function testAConfigurationErrorCarriesTheFailureItCameFrom(): void
    {
        $cause = new Exception('the file could not be parsed');

        self::assertSame($cause, (new ConfigException('unusable', 0, $cause))->getPrevious());
    }
}
