<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\DebugAnalyzer\Generator;

use App\Analyzer\DebugAnalyzer\Generator\RandomSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RandomSource::class)]
#[Small]
final class RandomSourceTest extends TestCase
{
    public function testNumberBetweenDrawsTheSameSequenceForTheSameSeedOnEveryRuntime(): void
    {
        $random = new RandomSource(7);

        self::assertSame([22, 37, 5, 40, 88, 35, 94, 84], array_map(static fn (): int => $random->numberBetween(0, 99), range(1, 8)));
    }

    public function testNumberBetweenNeverDrawsBelowTheSmallestBound(): void
    {
        $random = new RandomSource(42);

        self::assertSame(3, min(array_map(static fn (): int => $random->numberBetween(3, 5), range(1, 64))));
    }

    public function testNumberBetweenNeverDrawsAboveTheLargestBound(): void
    {
        $random = new RandomSource(42);

        self::assertSame(5, max(array_map(static fn (): int => $random->numberBetween(3, 5), range(1, 64))));
    }

    public function testNumberBetweenDrawsTheOnlyNumberTheBoundsLeaveOpen(): void
    {
        self::assertSame(5, (new RandomSource(42))->numberBetween(5, 5));
    }

    public function testNumberBetweenDrawsANegativeBoundAsWritten(): void
    {
        self::assertSame(-3, (new RandomSource(42))->numberBetween(-3, -3));
    }

    #[DataProvider('providerSeedsWithNothingToMixOn')]
    public function testASeedThatWouldLeaveTheSequenceStuckStillDraws(int $seed): void
    {
        $random = new RandomSource($seed);
        $drawn = array_map(static fn (): int => $random->numberBetween(0, 99), range(1, 8));

        self::assertNotSame(array_fill(0, 8, $drawn[0]), $drawn);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function providerSeedsWithNothingToMixOn(): iterable
    {
        yield 'zero' => [0];

        yield 'the mixing constant itself' => [0x9E3779B9];

        yield 'every bit set' => [-1];

        yield 'the largest integer' => [PHP_INT_MAX];
    }

    public function testADifferentSeedDrawsADifferentSequence(): void
    {
        $seven = new RandomSource(7);
        $nine = new RandomSource(9);

        self::assertNotSame(
            array_map(static fn (): int => $seven->numberBetween(0, 99), range(1, 8)),
            array_map(static fn (): int => $nine->numberBetween(0, 99), range(1, 8)),
        );
    }

    public function testBooleanDrawsBothOutcomes(): void
    {
        $random = new RandomSource(7);
        $drawn = array_map(static fn (): bool => $random->boolean(), range(1, 64));

        self::assertContains(true, $drawn);
        self::assertContains(false, $drawn);
    }

    public function testBooleanDrawsEachOutcomeAboutHalfTheTime(): void
    {
        $random = new RandomSource(7);
        $trues = count(array_filter(array_map(static fn (): bool => $random->boolean(), range(1, 1000))));

        self::assertGreaterThan(400, $trues);
        self::assertLessThan(600, $trues);
    }

    public function testWordDrawsTheSameWordsForTheSameSeed(): void
    {
        $random = new RandomSource(7);

        self::assertSame(['velit', 'saepe', 'saepe', 'ad'], array_map(static fn (): string => $random->word(), range(1, 4)));
    }

    public function testWordIsALowercaseWordEveryTime(): void
    {
        $random = new RandomSource(42);
        $drawn = array_map(static fn (): string => $random->word(), range(1, 64));

        self::assertSame($drawn, array_values(array_filter($drawn, static fn (string $word): bool => preg_match('/^[a-z]+$/', $word) === 1)));
    }

    public function testBooleanDrawsTheSameSequenceForTheSameSeed(): void
    {
        $random = new RandomSource(7);

        self::assertSame([false, true, true, false, false, true, false, false], array_map(static fn (): bool => $random->boolean(), range(1, 8)));
    }
}
