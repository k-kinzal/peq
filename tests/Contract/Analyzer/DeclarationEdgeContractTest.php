<?php

declare(strict_types=1);

namespace Tests\Contract\Analyzer;

use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\AnalysedSnippet;
use Tests\Fixture\Graph\GraphRelations;

/**
 * @internal
 */
#[CoversClass(PhpStanAnalyzer::class)]
#[Large]
final class DeclarationEdgeContractTest extends TestCase
{
    #[Test]
    public function testExtendsContract(): void
    {
        $code = <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace Tests\Contract\Analyzer\Generated;

            class Dep {}

            class Subject extends Dep {}
            PHP;

        $graph = AnalysedSnippet::graph($code);
        GraphRelations::assertRelationExists(
            $graph,
            'Subject',
            'Dep',
            EdgeKind::DeclarationExtends,
            'Contract violated: DeclarationExtends edge missing for class extends',
        );
    }

    #[Test]
    public function testImplementsContract(): void
    {
        $code = <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace Tests\Contract\Analyzer\Generated;

            interface DepInterface {}

            class Subject implements DepInterface {}
            PHP;

        $graph = AnalysedSnippet::graph($code);
        GraphRelations::assertRelationExists(
            $graph,
            'Subject',
            'DepInterface',
            EdgeKind::DeclarationImplements,
            'Contract violated: DeclarationImplements edge missing for class implements',
        );
    }

    #[Test]
    public function testTraitUseContract(): void
    {
        $code = <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace Tests\Contract\Analyzer\Generated;

            trait DepTrait {}

            class Subject {
                use DepTrait;
            }
            PHP;

        $graph = AnalysedSnippet::graph($code);
        GraphRelations::assertRelationExists(
            $graph,
            'Subject',
            'DepTrait',
            EdgeKind::DeclarationTraitUse,
            'Contract violated: DeclarationTraitUse edge missing for trait use',
        );
    }

    #[DataProvider('providerAttributeTargetVariations')]
    #[Test]
    public function testAttributeContract(string $label, string $code, string $fromSuffix): void
    {
        $graph = AnalysedSnippet::graph($code);
        GraphRelations::assertRelationExists(
            $graph,
            $fromSuffix,
            'DepAttr',
            EdgeKind::Attribute,
            "Contract violated [{$label}]: Attribute edge missing",
        );
    }

    /**
     * @return Generator<string, array{string, string, string}>
     */
    public static function providerAttributeTargetVariations(): Generator
    {
        yield 'class' => [
            'class',
            <<<'PHP'
                <?php
                declare(strict_types=1);
                namespace Tests\Contract\Analyzer\Generated;

                #[\Attribute]
                class DepAttr {}

                #[DepAttr]
                class Subject {}
                PHP,
            'Subject',
        ];

        yield 'method' => [
            'method',
            <<<'PHP'
                <?php
                declare(strict_types=1);
                namespace Tests\Contract\Analyzer\Generated;

                #[\Attribute]
                class DepAttr {}

                class Subject {
                    #[DepAttr]
                    public function testMethod(): void {}
                }
                PHP,
            'Subject::testMethod',
        ];

        yield 'property' => [
            'property',
            <<<'PHP'
                <?php
                declare(strict_types=1);
                namespace Tests\Contract\Analyzer\Generated;

                #[\Attribute]
                class DepAttr {}

                class Subject {
                    #[DepAttr]
                    public string $prop = '';
                }
                PHP,
            'Subject::prop',
        ];

        yield 'parameter' => [
            'parameter',
            <<<'PHP'
                <?php
                declare(strict_types=1);
                namespace Tests\Contract\Analyzer\Generated;

                #[\Attribute]
                class DepAttr {}

                class Subject {
                    public function testMethod(#[DepAttr] string $x): void {}
                }
                PHP,
            'Subject::testMethod',
        ];

        yield 'constant' => [
            'constant',
            <<<'PHP'
                <?php
                declare(strict_types=1);
                namespace Tests\Contract\Analyzer\Generated;

                #[\Attribute]
                class DepAttr {}

                class Subject {
                    #[DepAttr]
                    public const FOO = 'bar';
                }
                PHP,
            'Subject::FOO',
        ];
    }

    #[DataProvider('providerParameterTypeVariations')]
    #[Test]
    public function testParameterTypeContract(string $label, string $code): void
    {
        $graph = AnalysedSnippet::graph($code);
        GraphRelations::assertRelationExists(
            $graph,
            'Subject::testMethod',
            'Dep',
            EdgeKind::DeclarationTypeParameter,
            "Contract violated [{$label}]: DeclarationTypeParameter edge missing",
        );
    }

    /**
     * @return Generator<string, array{string, string}>
     */
    public static function providerParameterTypeVariations(): Generator
    {
        yield 'simple' => ['simple', AnalysedSnippet::asParameterType('Dep')];

        yield 'nullable' => ['nullable', AnalysedSnippet::asParameterType('?Dep')];

        yield 'union' => ['union', AnalysedSnippet::asParameterType('Dep|null')];

        yield 'intersection' => ['intersection', AnalysedSnippet::asParameterType('Dep&\Stringable')];
    }

    #[DataProvider('providerReturnTypeVariations')]
    #[Test]
    public function testReturnTypeContract(string $label, string $code): void
    {
        $graph = AnalysedSnippet::graph($code);
        GraphRelations::assertRelationExists(
            $graph,
            'Subject::testMethod',
            'Dep',
            EdgeKind::DeclarationTypeReturn,
            "Contract violated [{$label}]: DeclarationTypeReturn edge missing",
        );
    }

    /**
     * @return Generator<string, array{string, string}>
     */
    public static function providerReturnTypeVariations(): Generator
    {
        yield 'simple' => ['simple', AnalysedSnippet::asReturnType('Dep')];

        yield 'nullable' => ['nullable', AnalysedSnippet::asReturnType('?Dep')];

        yield 'union' => ['union', AnalysedSnippet::asReturnType('Dep|null')];

        yield 'intersection' => ['intersection', AnalysedSnippet::asReturnType('Dep&\Stringable')];
    }

    #[DataProvider('providerPropertyTypeVariations')]
    #[Test]
    public function testPropertyTypeContract(string $label, string $code): void
    {
        $graph = AnalysedSnippet::graph($code);
        GraphRelations::assertRelationExists(
            $graph,
            'Subject::prop',
            'Dep',
            EdgeKind::DeclarationTypeProperty,
            "Contract violated [{$label}]: DeclarationTypeProperty edge missing",
        );
    }

    /**
     * @return Generator<string, array{string, string}>
     */
    public static function providerPropertyTypeVariations(): Generator
    {
        yield 'simple' => ['simple', AnalysedSnippet::asPropertyType('Dep')];

        yield 'nullable' => ['nullable', AnalysedSnippet::asPropertyType('?Dep')];

        yield 'union' => ['union', AnalysedSnippet::asPropertyType('Dep|null')];

        yield 'intersection' => ['intersection', AnalysedSnippet::asPropertyType('Dep&\Stringable')];
    }
}
