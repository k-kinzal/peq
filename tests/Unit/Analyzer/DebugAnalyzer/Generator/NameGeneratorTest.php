<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\DebugAnalyzer\Generator;

use App\Analyzer\DebugAnalyzer\Generator\NameGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\SeededGenerators;

/**
 * @internal
 */
#[CoversClass(NameGenerator::class)]
#[Small]
final class NameGeneratorTest extends TestCase
{
    public function testPascalCaseStartsWithACapitalAndHasNoSeparators(): void
    {
        self::assertMatchesRegularExpression('/^[A-Z][A-Za-z]+$/', SeededGenerators::names()->pascalCase());
    }

    public function testCamelCaseStartsWithALowerCaseLetter(): void
    {
        self::assertMatchesRegularExpression('/^[a-z][A-Za-z]+$/', SeededGenerators::names()->camelCase());
    }

    public function testUpperSnakeCaseSeparatesItsWordsWithUnderscores(): void
    {
        self::assertMatchesRegularExpression('/^[A-Z]+(_[A-Z]+)+$/', SeededGenerators::names()->upperSnakeCase());
    }

    public function testNamespaceJoinsPascalCaseSegmentsWithBackslashes(): void
    {
        self::assertMatchesRegularExpression('/^[A-Z][A-Za-z]*(\\\[A-Z][A-Za-z]*)*$/', SeededGenerators::names()->namespace());
    }

    public function testClassNameEndsInTheKindOfSymbolItNames(): void
    {
        self::assertStringEndsWith('Class', SeededGenerators::names()->className());
    }

    public function testInterfaceNameEndsInTheKindOfSymbolItNames(): void
    {
        self::assertStringEndsWith('Interface', SeededGenerators::names()->interfaceName());
    }

    public function testTraitNameEndsInTheKindOfSymbolItNames(): void
    {
        self::assertStringEndsWith('Trait', SeededGenerators::names()->traitName());
    }

    public function testEnumNameEndsInTheKindOfSymbolItNames(): void
    {
        self::assertStringEndsWith('Enum', SeededGenerators::names()->enumName());
    }

    public function testMethodNameIsCamelCaseAsAMethodNameIs(): void
    {
        self::assertMatchesRegularExpression('/^[a-z][A-Za-z]+Method$/', SeededGenerators::names()->methodName());
    }

    public function testPropertyNameIsCamelCaseAsAPropertyNameIs(): void
    {
        self::assertMatchesRegularExpression('/^[a-z][A-Za-z]+Property$/', SeededGenerators::names()->propertyName());
    }

    public function testConstantNameIsUpperSnakeCaseAsAConstantNameIs(): void
    {
        self::assertMatchesRegularExpression('/^[A-Z_]+_CONST$/', SeededGenerators::names()->constantName());
    }

    public function testEnumCaseNameIsUpperSnakeCaseAsAnEnumCaseNameIs(): void
    {
        self::assertMatchesRegularExpression('/^[A-Z_]+_CASE$/', SeededGenerators::names()->enumCaseName());
    }

    public function testFunctionNameIsCamelCaseAsAFunctionNameIs(): void
    {
        self::assertMatchesRegularExpression('/^[a-z][A-Za-z]+Function$/', SeededGenerators::names()->functionName());
    }

    public function testPhpFilePathIsAnAbsolutePathToAPhpFile(): void
    {
        self::assertMatchesRegularExpression('#^(/[a-z]+)+/[A-Z][A-Za-z]*\.php$#', SeededGenerators::names()->phpFilePath());
    }

    public function testWordsDrawsAtLeastTheFewestAskedFor(): void
    {
        self::assertGreaterThanOrEqual(2, count(SeededGenerators::names()->words(static fn (string $word): string => $word, 2, 5)));
    }

    public function testWordsDrawsAtMostTheMostAskedFor(): void
    {
        self::assertLessThanOrEqual(5, count(SeededGenerators::names()->words(static fn (string $word): string => $word, 2, 5)));
    }

    public function testWordsShapesEveryWordItDraws(): void
    {
        $shouted = SeededGenerators::names()->words(static fn (string $word): string => strtoupper($word), 3, 3);

        self::assertSame($shouted, array_map('strtoupper', $shouted));
    }

    public function testTheSameSeedProducesTheSameName(): void
    {
        self::assertSame(SeededGenerators::names(7)->className(), SeededGenerators::names(7)->className());
    }

    public function testADifferentSeedProducesADifferentName(): void
    {
        self::assertNotSame(SeededGenerators::names(7)->namespace(), SeededGenerators::names(9)->namespace());
    }
}
