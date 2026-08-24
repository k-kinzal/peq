<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer;

use App\Analyzer\PhpStanAnalyzer\PhpStanAutoloader;
use Phar;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PhpStanAutoloader::class)]
#[Small]
final class PhpStanAutoloaderTest extends TestCase
{
    public function testEnsureRegisteredDoesNothingOutsideAPhar(): void
    {
        (new PhpStanAutoloader())->ensureRegistered();

        self::assertSame('', Phar::running());
    }

    public function testEnsureRegisteredLeavesPhpStanLoadableEitherWay(): void
    {
        (new PhpStanAutoloader())->ensureRegistered();

        self::assertTrue(interface_exists(\PHPStan\Analyser\Scope::class));
    }

    public function testExtractCopiesTheBundledArchiveOutOfTheRunningBinary(): void
    {
        self::assertStringEndsWith('/phpstan.phar', (new PhpStanAutoloader())->extract(__FILE__));
    }

    public function testExtractKeysItsCacheOnTheRunningBinary(): void
    {
        $autoloader = new PhpStanAutoloader();

        self::assertSame($autoloader->extract(__FILE__), $autoloader->extract(__FILE__));
    }

    public function testEnsureRegisteredIsSafeToCallSeveralTimes(): void
    {
        $autoloader = new PhpStanAutoloader();
        $autoloader->ensureRegistered();
        $autoloader->ensureRegistered();

        self::assertSame('', Phar::running());
    }
}
