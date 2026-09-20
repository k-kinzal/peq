<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Config\PhpVersion;

use const PHP_VERSION_ID;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PhpVersion::class)]
#[Small]
final class PhpVersionTest extends TestCase
{
    public function testTryFromStringReadsTheMinorVersionAProjectStatesItsPolicyIn(): void
    {
        self::assertSame(70100, PhpVersion::tryFromString('7.1')?->id);
    }

    public function testTryFromStringReadsAMajorVersionWrittenOnItsOwn(): void
    {
        self::assertSame(80000, PhpVersion::tryFromString('8')?->id);
    }

    public function testTryFromStringReadsThePatchOfAPatchRelease(): void
    {
        self::assertSame(80302, PhpVersion::tryFromString('8.3.2')?->id);
    }

    public function testTryFromStringReportsNothingForAVersionOlderThanAnyAnalyzerReads(): void
    {
        self::assertNull(PhpVersion::tryFromString('5.5'));
    }

    public function testTryFromStringReadsTheOldestVersionAnyAnalyzerStillReads(): void
    {
        self::assertSame(50600, PhpVersion::tryFromString('5.6')?->id);
    }

    public function testTryFromStringReportsNothingForAVersionNewerThanTheAnalysisReads(): void
    {
        self::assertNull(PhpVersion::tryFromString('9.0'));
    }

    public function testTryFromStringReportsNothingForTheVersionJustBelowTheOldestRead(): void
    {
        self::assertNull(PhpVersion::tryFromString('5.5.99'));
    }

    #[DataProvider('providerTextThatSpellsNoVersion')]
    public function testTryFromStringReportsNothingForTextThatSpellsNoVersion(string $value): void
    {
        self::assertNull(PhpVersion::tryFromString($value));
    }

    #[DataProvider('providerTextThatSpellsNoVersion')]
    public function testParseReportsNothingForTextThatSpellsNoVersion(string $value): void
    {
        self::assertNull(PhpVersion::parse($value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerTextThatSpellsNoVersion(): iterable
    {
        yield 'a word' => ['latest'];

        yield 'nothing at all' => [''];

        yield 'a wildcard minor' => ['8.x'];

        yield 'a constraint rather than a version' => ['^8.3'];

        yield 'a negative number' => ['-1'];

        yield 'more parts than a version has' => ['8.3.2.1'];

        yield 'a version with spaces around it' => [' 8.3 '];
    }

    public function testParseReadsAVersionTheAnalysisCannotBeAskedFor(): void
    {
        self::assertSame(50600, PhpVersion::parse('5.6'));
    }

    public function testParseCountsTheMinorInHundreds(): void
    {
        self::assertSame(80400, PhpVersion::parse('8.4'));
    }

    public function testParseCountsTheMajorInTenThousands(): void
    {
        self::assertSame(70000, PhpVersion::parse('7'));
    }

    public function testHostIsTheVersionPeqRunsOnAsFarAsTheAnalysisReadsIt(): void
    {
        self::assertSame(min(PHP_VERSION_ID, PhpVersion::NEWEST_SUPPORTED), PhpVersion::host()->id);
    }

    public function testHostIsNeverOlderThanTheOldestVersionTheAnalysisReads(): void
    {
        self::assertGreaterThanOrEqual(PhpVersion::OLDEST_SUPPORTED, PhpVersion::host()->id);
    }

    public function testOldestIsTheOldestVersionTheAnalysisReads(): void
    {
        self::assertSame(50600, PhpVersion::oldest()->id);
    }

    public function testNewestIsTheNewestVersionTheAnalysisReads(): void
    {
        self::assertSame(80599, PhpVersion::newest()->id);
    }

    public function testToStringWritesTheMinorVersionAPolicyIsStatedIn(): void
    {
        self::assertSame('8.3', (new PhpVersion(80300))->toString());
    }

    public function testToStringWritesAPatchReleaseAsTheMinorItBelongsTo(): void
    {
        self::assertSame('8.3', (new PhpVersion(80302))->toString());
    }

    public function testToStringWritesTheMajorAndTheMinorApart(): void
    {
        self::assertSame('7.4', (new PhpVersion(70400))->toString());
    }

    #[DataProvider('providerEverySupportedMinorVersion')]
    public function testEverySupportedMinorVersionIsReadBackAsItWasWritten(string $version): void
    {
        self::assertSame($version, PhpVersion::tryFromString($version)?->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerEverySupportedMinorVersion(): iterable
    {
        foreach (['5.6', '7.1', '7.2', '7.3', '7.4', '8.0', '8.1', '8.2', '8.3', '8.4', '8.5'] as $version) {
            yield 'PHP '.$version => [$version];
        }
    }
}
