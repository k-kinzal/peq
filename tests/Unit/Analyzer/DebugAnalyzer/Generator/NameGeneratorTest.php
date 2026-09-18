<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\DebugAnalyzer\Generator;

use App\Analyzer\DebugAnalyzer\Generator\NameGenerator;
use App\Analyzer\DebugAnalyzer\Generator\RandomSource;
use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(NameGenerator::class)]
#[UsesClass(RandomSource::class)]
#[Small]
final class NameGeneratorTest extends TestCase
{
    #[DataProvider('providerNameGenerator')]
    public function testPascalCaseStartsWithACapitalAndHasNoSeparators(NameGenerator $names): void
    {
        self::assertMatchesRegularExpression('/^[A-Z][A-Za-z]+$/', $names->pascalCase());
    }

    #[DataProvider('providerNameGenerator')]
    public function testCamelCaseStartsWithALowerCaseLetter(NameGenerator $names): void
    {
        self::assertMatchesRegularExpression('/^[a-z][A-Za-z]+$/', $names->camelCase());
    }

    #[DataProvider('providerNameGenerator')]
    public function testUpperSnakeCaseSeparatesItsWordsWithUnderscores(NameGenerator $names): void
    {
        self::assertMatchesRegularExpression('/^[A-Z]+(_[A-Z]+)+$/', $names->upperSnakeCase());
    }

    #[DataProvider('providerNameGenerator')]
    public function testNamespaceJoinsPascalCaseSegmentsWithBackslashes(NameGenerator $names): void
    {
        self::assertMatchesRegularExpression('/^[A-Z][A-Za-z]*(\\\[A-Z][A-Za-z]*)*$/', $names->namespace());
    }

    #[DataProvider('providerNameGenerator')]
    public function testClassNameEndsInTheKindOfSymbolItNames(NameGenerator $names): void
    {
        self::assertStringEndsWith('Class', $names->className());
    }

    #[DataProvider('providerNameGenerator')]
    public function testInterfaceNameEndsInTheKindOfSymbolItNames(NameGenerator $names): void
    {
        self::assertStringEndsWith('Interface', $names->interfaceName());
    }

    #[DataProvider('providerNameGenerator')]
    public function testTraitNameEndsInTheKindOfSymbolItNames(NameGenerator $names): void
    {
        self::assertStringEndsWith('Trait', $names->traitName());
    }

    #[DataProvider('providerNameGenerator')]
    public function testEnumNameEndsInTheKindOfSymbolItNames(NameGenerator $names): void
    {
        self::assertStringEndsWith('Enum', $names->enumName());
    }

    #[DataProvider('providerNameGenerator')]
    public function testMethodNameIsCamelCaseAsAMethodNameIs(NameGenerator $names): void
    {
        self::assertMatchesRegularExpression('/^[a-z][A-Za-z]+Method$/', $names->methodName());
    }

    #[DataProvider('providerNameGenerator')]
    public function testPropertyNameIsCamelCaseAsAPropertyNameIs(NameGenerator $names): void
    {
        self::assertMatchesRegularExpression('/^[a-z][A-Za-z]+Property$/', $names->propertyName());
    }

    #[DataProvider('providerNameGenerator')]
    public function testConstantNameIsUpperSnakeCaseAsAConstantNameIs(NameGenerator $names): void
    {
        self::assertMatchesRegularExpression('/^[A-Z_]+_CONST$/', $names->constantName());
    }

    #[DataProvider('providerNameGenerator')]
    public function testEnumCaseNameIsUpperSnakeCaseAsAnEnumCaseNameIs(NameGenerator $names): void
    {
        self::assertMatchesRegularExpression('/^[A-Z_]+_CASE$/', $names->enumCaseName());
    }

    #[DataProvider('providerNameGenerator')]
    public function testFunctionNameIsCamelCaseAsAFunctionNameIs(NameGenerator $names): void
    {
        self::assertMatchesRegularExpression('/^[a-z][A-Za-z]+Function$/', $names->functionName());
    }

    #[DataProvider('providerNameGenerator')]
    public function testPhpFilePathIsAnAbsolutePathToAPhpFile(NameGenerator $names): void
    {
        self::assertMatchesRegularExpression('#^(/[a-z]+)+/[A-Z][A-Za-z]*\.php$#', $names->phpFilePath());
    }

    #[DataProvider('providerNameGenerator')]
    public function testWordsDrawsAtLeastTheFewestAskedFor(NameGenerator $names): void
    {
        self::assertGreaterThanOrEqual(2, count($names->words(static fn (string $word): string => $word, 2, 5)));
    }

    #[DataProvider('providerNameGenerator')]
    public function testWordsDrawsAtMostTheMostAskedFor(NameGenerator $names): void
    {
        self::assertLessThanOrEqual(5, count($names->words(static fn (string $word): string => $word, 2, 5)));
    }

    #[DataProvider('providerNameGenerator')]
    public function testWordsShapesEveryWordItDraws(NameGenerator $names): void
    {
        $shouted = $names->words(static fn (string $word): string => strtoupper($word), 3, 3);

        self::assertSame($shouted, array_map('strtoupper', $shouted));
    }

    /**
     * @return iterable<string, array{NameGenerator}>
     */
    public static function providerNameGenerator(): iterable
    {
        yield 'drawing from seed 42' => [new NameGenerator(new RandomSource(42))];
    }

    public function testTheSameSeedProducesTheSameName(): void
    {
        self::assertSame((new NameGenerator(new RandomSource(7)))->className(), (new NameGenerator(new RandomSource(7)))->className());
    }

    public function testADifferentSeedProducesADifferentName(): void
    {
        self::assertNotSame((new NameGenerator(new RandomSource(7)))->namespace(), (new NameGenerator(new RandomSource(9)))->namespace());
    }

    /**
     * @param Closure(NameGenerator): string $name
     */
    #[DataProvider('providerNamesDrawnFromSeedSeven')]
    public function testEveryNameIsSpelledFromTheDrawsOfItsSeed(Closure $name, string $expected): void
    {
        self::assertSame($expected, $name(new NameGenerator(new RandomSource(7))));
    }

    /**
     * @return iterable<string, array{Closure(NameGenerator): string, string}>
     */
    public static function providerNamesDrawnFromSeedSeven(): iterable
    {
        yield 'pascalCase' => [static fn (NameGenerator $names): string => $names->pascalCase(), 'SaepeSaepe'];

        yield 'camelCase' => [static fn (NameGenerator $names): string => $names->camelCase(), 'saepeSaepe'];

        yield 'upperSnakeCase' => [static fn (NameGenerator $names): string => $names->upperSnakeCase(), 'SAEPE_SAEPE'];

        yield 'namespace' => [static fn (NameGenerator $names): string => $names->namespace(), 'SaepeAdRerum\SedEnimDolor'];

        yield 'className' => [static fn (NameGenerator $names): string => $names->className(), 'SaepeSaepeClass'];

        yield 'interfaceName' => [static fn (NameGenerator $names): string => $names->interfaceName(), 'SaepeSaepeInterface'];

        yield 'traitName' => [static fn (NameGenerator $names): string => $names->traitName(), 'SaepeSaepeTrait'];

        yield 'enumName' => [static fn (NameGenerator $names): string => $names->enumName(), 'SaepeSaepeEnum'];

        yield 'methodName' => [static fn (NameGenerator $names): string => $names->methodName(), 'saepeSaepeMethod'];

        yield 'propertyName' => [static fn (NameGenerator $names): string => $names->propertyName(), 'saepeSaepeProperty'];

        yield 'constantName' => [static fn (NameGenerator $names): string => $names->constantName(), 'SAEPE_SAEPE_CONST'];

        yield 'enumCaseName' => [static fn (NameGenerator $names): string => $names->enumCaseName(), 'SAEPE_SAEPE_CASE'];

        yield 'functionName' => [static fn (NameGenerator $names): string => $names->functionName(), 'saepeSaepeFunction'];

        yield 'phpFilePath' => [static fn (NameGenerator $names): string => $names->phpFilePath(), '/saepe/saepe/ad/HarumSed.php'];
    }

    public function testWordsDrawsTheCountAndThenTheWordsOfItsSeed(): void
    {
        self::assertSame(['saepe', 'saepe', 'ad'], (new NameGenerator(new RandomSource(7)))->words(static fn (string $word): string => $word, 1, 4));
    }

    public function testWordsShapesTheWordsOfItsSeed(): void
    {
        self::assertSame(['SAEPE', 'SAEPE', 'AD'], (new NameGenerator(new RandomSource(7)))->words(static fn (string $word): string => strtoupper($word), 3, 3));
    }
}
