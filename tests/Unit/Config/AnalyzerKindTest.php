<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Config\AnalyzerKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AnalyzerKind::class)]
#[Small]
final class AnalyzerKindTest extends TestCase
{
    public function testKindsAreSpelledTheWayTheCommandLineSpellsThem(): void
    {
        self::assertSame('phpstan', AnalyzerKind::PhpStan->value);
        self::assertSame('debug', AnalyzerKind::Debug->value);
    }

    public function testTheChoiceOfAnalyzerIsClosed(): void
    {
        self::assertSame([AnalyzerKind::PhpStan, AnalyzerKind::Debug], AnalyzerKind::cases());
    }

    public function testAWrittenKindResolvesToItsCase(): void
    {
        self::assertSame(AnalyzerKind::Debug, AnalyzerKind::from('debug'));
    }

    public function testAnUnknownKindResolvesToNothing(): void
    {
        self::assertNull(AnalyzerKind::tryFrom('reflection'));
    }
}
