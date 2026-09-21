<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Config\AnalyzerKind;
use App\Config\PhpVersion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AnalyzerKind::class)]
#[UsesClass(PhpVersion::class)]
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

    #[DataProvider('providerWhatAKindIsBuiltOn')]
    public function testRequiresNamesWhatTheKindIsBuiltOn(AnalyzerKind $kind, ?string $expected): void
    {
        self::assertSame($expected, $kind->requires());
    }

    /**
     * @return iterable<string, array{AnalyzerKind, null|string}>
     */
    public static function providerWhatAKindIsBuiltOn(): iterable
    {
        yield 'the analyzer built on PHPStan needs PHPStan' => [AnalyzerKind::PhpStan, 'PHPStan\DependencyInjection\ContainerFactory'];

        yield 'the analyzer that reads sources directly needs nothing' => [AnalyzerKind::Native, null];

        yield 'the analyzer that invents a graph needs nothing' => [AnalyzerKind::Debug, null];
    }

    public function testIsAvailableHoldsForAnAnalyzerThatNeedsNothingInstalled(): void
    {
        self::assertTrue(AnalyzerKind::Native->isAvailable());
    }

    public function testIsAvailableFollowsWhetherWhatTheKindIsBuiltOnIsInstalled(): void
    {
        self::assertSame(class_exists((string) AnalyzerKind::PhpStan->requires()), AnalyzerKind::PhpStan->isAvailable());
    }

    public function testIsAvailableHoldsForAnAnalyzerThisCheckoutHasInstalled(): void
    {
        self::assertTrue(AnalyzerKind::PhpStan->isAvailable(), 'This checkout installs PHPStan, so the analyzer built on it can run here.');
    }

    public function testAvailableLeavesOutAKindThisInstallationCannotRun(): void
    {
        self::assertSame(
            array_values(array_filter(AnalyzerKind::cases(), static fn (AnalyzerKind $kind): bool => $kind->isAvailable())),
            AnalyzerKind::available(),
        );
    }

    public function testAvailableAlwaysHoldsTheAnalyzerThatNeedsNothingInstalled(): void
    {
        self::assertContains(AnalyzerKind::Native, AnalyzerKind::available());
    }

    public function testPreferredIsTheFirstKindThisInstallationCanRun(): void
    {
        self::assertSame(AnalyzerKind::available()[0], AnalyzerKind::preferred());
    }

    public function testPreferredIsTheReferenceEngineWhereItIsInstalled(): void
    {
        self::assertSame(AnalyzerKind::PhpStan, AnalyzerKind::preferred(), 'This checkout installs PHPStan, so it is what an unasked-for analyzer means here.');
    }

    public function testSpellAvailableWritesTheKindsTheCommandLineWillTake(): void
    {
        self::assertSame(
            implode('|', array_map(static fn (AnalyzerKind $kind): string => $kind->value, AnalyzerKind::available())),
            AnalyzerKind::spellAvailable(),
        );
    }

    public function testSpellAvailableLeavesOutAKindThisInstallationCannotRun(): void
    {
        self::assertStringNotContainsString('nonesuch', AnalyzerKind::spellAvailable());
    }

    public function testSpellAvailableWritesEveryKindThisCheckoutCanRun(): void
    {
        self::assertSame('phpstan|native|debug', AnalyzerKind::spellAvailable());
    }

    public function testOldestPhpVersionOfTheEngineReadingSourcesDirectlyIsTheOldestPeqReads(): void
    {
        self::assertSame(PhpVersion::OLDEST_SUPPORTED, AnalyzerKind::Native->oldestPhpVersion()->id);
    }

    public function testOldestPhpVersionOfTheReferenceEngineIsTheOldestPhpStanAnalyses(): void
    {
        self::assertSame(70100, AnalyzerKind::PhpStan->oldestPhpVersion()->id);
    }

    public function testOldestPhpVersionOfTheEngineThatReadsNoSourceBindsNothing(): void
    {
        self::assertSame(PhpVersion::OLDEST_SUPPORTED, AnalyzerKind::Debug->oldestPhpVersion()->id);
    }
}
