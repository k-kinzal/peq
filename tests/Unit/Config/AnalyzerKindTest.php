<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Config\AnalyzerKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
        self::assertSame('native', AnalyzerKind::Native->value);
        self::assertSame('debug', AnalyzerKind::Debug->value);
    }

    public function testTheChoiceOfAnalyzerIsClosed(): void
    {
        self::assertSame([AnalyzerKind::PhpStan, AnalyzerKind::Native, AnalyzerKind::Debug], AnalyzerKind::cases());
    }

    public function testAWrittenKindResolvesToItsCase(): void
    {
        self::assertSame(AnalyzerKind::Debug, AnalyzerKind::from('debug'));
    }

    #[DataProvider('providerUnknownKinds')]
    public function testAnUnknownKindResolvesToNothing(string $written): void
    {
        self::assertNull(AnalyzerKind::tryFrom($written));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerUnknownKinds(): iterable
    {
        yield 'reflection' => ['reflection'];

        yield 'nothing written' => [''];

        yield 'DEBUG' => ['DEBUG'];

        yield 'php-stan' => ['php-stan'];
    }
}
