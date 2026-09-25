<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer;

use App\Analyzer\ExecutionVersion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ExecutionVersion::class)]
#[Small]
final class ExecutionVersionTest extends TestCase
{
    public function testCurrentAReleaseUsesTheVersionBoxEmbedded(): void
    {
        self::assertSame('v1.2.3', ExecutionVersion::current('v1.2.3'));
        self::assertSame('v1.2.4', ExecutionVersion::current('v1.2.4'));
    }

    public function testCurrentSourceInstallationsHaveAStableContentVersion(): void
    {
        self::assertMatchesRegularExpression('/^source-[a-f0-9]{64}$/', ExecutionVersion::current());
        self::assertSame(ExecutionVersion::current(), ExecutionVersion::current());
    }
}
