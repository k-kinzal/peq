<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Analyzer\Graph\Direction;
use App\Config\AnalyzerKind;
use App\Config\ConfigException;
use App\Config\ConfigReader;
use App\Config\RawConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @phpstan-import-type ConfigField from ConfigReader
 */
#[CoversClass(RawConfig::class)]
#[Small]
final class RawConfigTest extends TestCase
{
    public function testHasReportsASettingTheSourceMentioned(): void
    {
        self::assertTrue((new RawConfig(['level' => 3]))->has('level'));
    }

    public function testHasReportsASettingMentionedWithNoValue(): void
    {
        self::assertTrue((new RawConfig(['level' => null]))->has('level'));
    }

    public function testHasReportsNothingForASettingTheSourceLeftOut(): void
    {
        self::assertFalse((new RawConfig([]))->has('level'));
    }

    /**
     * @throws ConfigException
     */
    public function testRequiredStringReadsAStringSetting(): void
    {
        self::assertSame('/project', (new RawConfig(['basePath' => '/project']))->requiredString('basePath'));
    }

    /**
     * @throws ConfigException
     */
    public function testRequiredStringRejectsAMissingSetting(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Invalid configuration "basePath"');

        (new RawConfig([]))->requiredString('basePath');
    }

    /**
     * @throws ConfigException
     */
    public function testRequiredStringRejectsAnEmptySetting(): void
    {
        $this->expectException(ConfigException::class);

        (new RawConfig(['basePath' => '']))->requiredString('basePath');
    }

    /**
     * @throws ConfigException
     */
    public function testRequiredStringRejectsAValueOfAnotherType(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('got 3 (int)');

        (new RawConfig(['basePath' => 3]))->requiredString('basePath');
    }

    /**
     * @throws ConfigException
     */
    public function testOptionalIntReadsANumberTheSourceTyped(): void
    {
        self::assertSame(3, (new RawConfig(['level' => 3]))->optionalInt('level'));
    }

    /**
     * @throws ConfigException
     */
    public function testOptionalIntReadsANumberTheSourceCouldOnlyCarryAsText(): void
    {
        self::assertSame(3, (new RawConfig(['level' => '3']))->optionalInt('level'));
    }

    /**
     * @throws ConfigException
     */
    public function testOptionalIntReadsANegativeNumber(): void
    {
        self::assertSame(-7, (new RawConfig(['seed' => '-7']))->optionalInt('seed'));
    }

    /**
     * @throws ConfigException
     */
    public function testOptionalIntReadsNothingForASettingLeftUnset(): void
    {
        self::assertNull((new RawConfig([]))->optionalInt('level'));
    }

    /**
     * @throws ConfigException
     */
    public function testOptionalIntRejectsTextThatIsNotANumber(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('expected an integer, got the string "deep"');

        (new RawConfig(['level' => 'deep']))->optionalInt('level');
    }

    /**
     * @throws ConfigException
     */
    public function testOptionalPositiveIntReadsAPositiveNumber(): void
    {
        self::assertSame(3, (new RawConfig(['level' => '3']))->optionalPositiveInt('level'));
    }

    /**
     * @throws ConfigException
     */
    public function testOptionalPositiveIntRejectsZero(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('expected a positive integer, got 0');

        (new RawConfig(['level' => 0]))->optionalPositiveInt('level');
    }

    /**
     * @throws ConfigException
     */
    public function testOptionalPositiveIntRejectsANegativeNumber(): void
    {
        $this->expectException(ConfigException::class);

        (new RawConfig(['level' => -1]))->optionalPositiveInt('level');
    }

    /**
     * @throws ConfigException
     */
    public function testRequiredPositiveIntReadsAPositiveNumber(): void
    {
        self::assertSame(5, (new RawConfig(['depth' => 5]))->requiredPositiveInt('depth'));
    }

    /**
     * @throws ConfigException
     */
    public function testRequiredPositiveIntRejectsAMissingSetting(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('got nothing');

        (new RawConfig([]))->requiredPositiveInt('depth');
    }

    /**
     * @throws ConfigException
     */
    public function testStringListReadsAListOfNames(): void
    {
        self::assertSame(['vendor', 'build'], (new RawConfig(['excludes' => ['vendor', 'build']]))->stringList('excludes'));
    }

    /**
     * @throws ConfigException
     */
    public function testStringListReadsAnEmptyListForASettingLeftUnset(): void
    {
        self::assertSame([], (new RawConfig([]))->stringList('excludes'));
    }

    /**
     * @throws ConfigException
     */
    public function testStringListRejectsASingleValueWhereAListIsMeant(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('expected a list of strings');

        (new RawConfig(['excludes' => 'vendor']))->stringList('excludes');
    }

    /**
     * @throws ConfigException
     */
    public function testStringListRejectsAnEmptyEntry(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('every entry must be a non-empty string');

        (new RawConfig(['excludes' => ['vendor', '']]))->stringList('excludes');
    }

    /**
     * @throws ConfigException
     */
    public function testEnumReadsACaseTheSourceNamed(): void
    {
        self::assertSame(Direction::UsedBy, (new RawConfig(['direction' => 'used-by']))->enum('direction', Direction::class));
    }

    /**
     * @throws ConfigException
     */
    public function testEnumRejectsAValueNamingNoCaseAndSaysWhatIsAllowed(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('expected one of uses, used-by, got "sideways"');

        (new RawConfig(['direction' => 'sideways']))->enum('direction', Direction::class);
    }

    /**
     * @throws ConfigException
     */
    public function testEnumRejectsAMissingSetting(): void
    {
        $this->expectException(ConfigException::class);

        (new RawConfig([]))->enum('type', AnalyzerKind::class);
    }

    /**
     * @throws ConfigException
     */
    public function testNestedReadsAGroupOfSettings(): void
    {
        self::assertSame(9, (new RawConfig(['debug' => ['depth' => 9]]))->nested('debug')->optionalInt('depth'));
    }

    /**
     * @throws ConfigException
     */
    public function testNestedReadsAnEmptyGroupForASettingLeftUnset(): void
    {
        self::assertFalse((new RawConfig([]))->nested('debug')->has('depth'));
    }

    /**
     * @throws ConfigException
     */
    public function testNestedRejectsASingleValueWhereAGroupIsMeant(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('expected a group of settings');

        (new RawConfig(['debug' => 'yes']))->nested('debug');
    }

    /**
     * @throws ConfigException
     */
    public function testNestedRejectsAGroupWhoseSettingsAreUnnamed(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('every setting must be named');

        (new RawConfig(['debug' => ['depth']]))->nested('debug');
    }

    /**
     * @throws ConfigException
     */
    public function testOptionalPhpVersionReadsTheVersionASourceNames(): void
    {
        self::assertSame(70100, (new RawConfig(['phpVersion' => '7.1']))->optionalPhpVersion('phpVersion')?->id);
    }

    /**
     * @throws ConfigException
     */
    public function testOptionalPhpVersionReadsNothingForASettingTheSourceLeftOut(): void
    {
        self::assertNull((new RawConfig([]))->optionalPhpVersion('phpVersion'));
    }

    /**
     * @throws ConfigException
     */
    public function testOptionalPhpVersionReadsNothingForASettingLeftWithNoValue(): void
    {
        self::assertNull((new RawConfig(['phpVersion' => null]))->optionalPhpVersion('phpVersion'));
    }

    /**
     * @throws ConfigException
     */
    public function testOptionalPhpVersionRejectsAVersionTheAnalysisCannotRead(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Invalid configuration "phpVersion": expected a PHP version between 7.1 and 8.5, got "5.6".');

        (new RawConfig(['phpVersion' => '5.6']))->optionalPhpVersion('phpVersion');
    }

    /**
     * @throws ConfigException
     */
    public function testOptionalPhpVersionRejectsTextThatNamesNoVersion(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('expected a PHP version between 7.1 and 8.5, got "latest"');

        (new RawConfig(['phpVersion' => 'latest']))->optionalPhpVersion('phpVersion');
    }

    /**
     * @throws ConfigException
     */
    public function testOptionalPhpVersionRejectsAVersionWrittenAsANumber(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('expected a PHP version written as text, such as "7.1", got 8.3 (float)');

        (new RawConfig(['phpVersion' => 8.3]))->optionalPhpVersion('phpVersion');
    }

    /**
     * @param null|ConfigField $value
     */
    #[DataProvider('providerRejectedValuesAndTheirDescriptions')]
    public function testDescribeNamesWhatWasFoundWithoutDumpingIt(array|bool|float|int|string|null $value, string $expected): void
    {
        self::assertSame($expected, RawConfig::describe($value));
    }

    /**
     * @return iterable<string, array{null|ConfigField, string}>
     */
    public static function providerRejectedValuesAndTheirDescriptions(): iterable
    {
        yield 'a string' => ['deep', 'the string "deep"'];

        yield 'an integer' => [3, '3 (int)'];

        yield 'a boolean' => [true, 'true (bool)'];

        yield 'a list' => [[1, 2], 'array'];

        yield 'nothing' => [null, 'null'];
    }
}
