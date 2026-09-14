<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\DebugAnalyzer\Generator;

use App\Analyzer\DebugAnalyzer\Generator\FakerRandomSource;
use App\Analyzer\DebugAnalyzer\Generator\RandomSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\DebugAnalyzer\PortableDraws;
use Tests\Fixture\Analyzer\DebugAnalyzer\SeededGenerators;

/**
 * @internal
 */
#[CoversClass(FakerRandomSource::class)]
#[Small]
final class RandomSourceTest extends TestCase
{
    #[DataProvider('providerEverySource')]
    public function testNumberBetweenNeverDrawsBelowTheSmallestBound(RandomSource $random): void
    {
        self::assertGreaterThanOrEqual(3, min(array_map(static fn (): int => $random->numberBetween(3, 5), range(1, 64))));
    }

    #[DataProvider('providerEverySource')]
    public function testNumberBetweenNeverDrawsAboveTheLargestBound(RandomSource $random): void
    {
        self::assertLessThanOrEqual(5, max(array_map(static fn (): int => $random->numberBetween(3, 5), range(1, 64))));
    }

    #[DataProvider('providerEverySource')]
    public function testNumberBetweenReachesBothBounds(RandomSource $random): void
    {
        $drawn = array_map(static fn (): int => $random->numberBetween(3, 5), range(1, 64));

        self::assertSame([3, 5], [min($drawn), max($drawn)]);
    }

    #[DataProvider('providerEverySource')]
    public function testNumberBetweenDrawsTheOnlyNumberTheBoundsLeaveOpen(RandomSource $random): void
    {
        self::assertSame(5, $random->numberBetween(5, 5));
    }

    #[DataProvider('providerEverySource')]
    public function testBooleanCanDrawTrue(RandomSource $random): void
    {
        self::assertContains(true, array_map(static fn (): bool => $random->boolean(), range(1, 64)));
    }

    #[DataProvider('providerEverySource')]
    public function testBooleanCanDrawFalse(RandomSource $random): void
    {
        self::assertContains(false, array_map(static fn (): bool => $random->boolean(), range(1, 64)));
    }

    #[DataProvider('providerEverySource')]
    public function testWordIsASingleLowercaseWord(RandomSource $random): void
    {
        self::assertMatchesRegularExpression('/^[a-z]+$/', $random->word());
    }

    /**
     * @return iterable<string, array{RandomSource}>
     */
    public static function providerEverySource(): iterable
    {
        yield 'the Faker source peq draws from' => [SeededGenerators::random()];

        yield 'the portable source graphs are recorded from' => [new PortableDraws(42)];
    }
}
