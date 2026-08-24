<?php

declare(strict_types=1);

namespace Tests\Contract\Analyzer;

use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\Graph;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Contract tests verifying that the analyzer correctly detects declaration-level
 * dependency edges (extends, implements, trait use, attributes, and type declarations)
 */#[CoversClass(PhpStanAnalyzer::class)]
#[Large]
final class DeclarationEdgeContractTest extends TestCase
{
    /**
     * Checks that extends contract.
     */
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

        $graph = self::analyzeCode($code);
        self::assertEdgeExists(
            $graph,
            'Subject',
            'Dep',
            EdgeKind::DeclarationExtends,
            'Contract violated: DeclarationExtends edge missing for class extends',
        );
    }

    /**
     * Checks that implements contract.
     */
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

        $graph = self::analyzeCode($code);
        self::assertEdgeExists(
            $graph,
            'Subject',
            'DepInterface',
            EdgeKind::DeclarationImplements,
            'Contract violated: DeclarationImplements edge missing for class implements',
        );
    }

    /**
     * Checks that trait use contract.
     */
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

        $graph = self::analyzeCode($code);
        self::assertEdgeExists(
            $graph,
            'Subject',
            'DepTrait',
            EdgeKind::DeclarationTraitUse,
            'Contract violated: DeclarationTraitUse edge missing for trait use',
        );
    }

    /**
     * Checks that attribute contract.
     */
    #[DataProvider('provideAttributeTargetVariations')]
    #[Test]
    public function testAttributeContract(string $label, string $code, string $fromSuffix): void
    {
        $graph = self::analyzeCode($code);
        self::assertEdgeExists(
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
    public static function provideAttributeTargetVariations(): Generator
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

    /**
     * Checks that parameter type contract.
     */
    #[DataProvider('provideParameterTypeVariations')]
    #[Test]
    public function testParameterTypeContract(string $label, string $code): void
    {
        $graph = self::analyzeCode($code);
        self::assertEdgeExists(
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
    public static function provideParameterTypeVariations(): Generator
    {
        yield 'simple' => ['simple', self::makeParameterTypeCode('Dep')];

        yield 'nullable' => ['nullable', self::makeParameterTypeCode('?Dep')];

        yield 'union' => ['union', self::makeParameterTypeCode('Dep|null')];

        yield 'intersection' => ['intersection', self::makeParameterTypeCode('Dep&\Stringable')];
    }

    /**
     * Checks that return type contract.
     */
    #[DataProvider('provideReturnTypeVariations')]
    #[Test]
    public function testReturnTypeContract(string $label, string $code): void
    {
        $graph = self::analyzeCode($code);
        self::assertEdgeExists(
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
    public static function provideReturnTypeVariations(): Generator
    {
        yield 'simple' => ['simple', self::makeReturnTypeCode('Dep')];

        yield 'nullable' => ['nullable', self::makeReturnTypeCode('?Dep')];

        yield 'union' => ['union', self::makeReturnTypeCode('Dep|null')];

        yield 'intersection' => ['intersection', self::makeReturnTypeCode('Dep&\Stringable')];
    }

    /**
     * Checks that property type contract.
     */
    #[DataProvider('providePropertyTypeVariations')]
    #[Test]
    public function testPropertyTypeContract(string $label, string $code): void
    {
        $graph = self::analyzeCode($code);
        self::assertEdgeExists(
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
    public static function providePropertyTypeVariations(): Generator
    {
        yield 'simple' => ['simple', self::makePropertyTypeCode('Dep')];

        yield 'nullable' => ['nullable', self::makePropertyTypeCode('?Dep')];

        yield 'union' => ['union', self::makePropertyTypeCode('Dep|null')];

        yield 'intersection' => ['intersection', self::makePropertyTypeCode('Dep&\Stringable')];
    }

    /**
     * Returns the make parameter type code a check needs.
     */
    public static function makeParameterTypeCode(string $typeHint): string
    {
        return <<<PHP
            <?php
            declare(strict_types=1);
            namespace Tests\\Contract\\Analyzer\\Generated;

            class Dep implements \\Stringable {
                public function __toString(): string { return ''; }
            }

            class Subject {
                public function testMethod({$typeHint} \$x): void {}
            }
            PHP;
    }

    /**
     * Returns the make return type code a check needs.
     */
    public static function makeReturnTypeCode(string $typeHint): string
    {
        return <<<PHP
            <?php
            declare(strict_types=1);
            namespace Tests\\Contract\\Analyzer\\Generated;

            class Dep implements \\Stringable {
                public function __toString(): string { return ''; }
            }

            class Subject {
                public function testMethod(): {$typeHint} { return new Dep(); }
            }
            PHP;
    }

    /**
     * Returns the make property type code a check needs.
     */
    public static function makePropertyTypeCode(string $typeHint): string
    {
        return <<<PHP
            <?php
            declare(strict_types=1);
            namespace Tests\\Contract\\Analyzer\\Generated;

            class Dep implements \\Stringable {
                public function __toString(): string { return ''; }
            }

            class Subject {
                public {$typeHint} \$prop;
            }
            PHP;
    }

    /**
     * Returns the analyze code a check needs.
     */
    public static function analyzeCode(string $phpCode): Graph
    {
        $tmpDir = sys_get_temp_dir().'/peq_contract_'.uniqid();
        mkdir($tmpDir, 0o777, true);
        file_put_contents($tmpDir.'/Test.php', $phpCode);

        try {
            $analyzer = new PhpStanAnalyzer();

            return $analyzer->analyze($tmpDir);
        } finally {
            @unlink($tmpDir.'/Test.php');
            @rmdir($tmpDir);
        }
    }

    /**
     * Returns the assert edge exists a check needs.
     */
    public function assertEdgeExists(
        Graph $graph,
        string $fromSuffix,
        string $toSuffix,
        EdgeKind $kind,
        string $msg,
    ): void {
        foreach ($graph->nodes() as $node) {
            if (!str_ends_with($node->id()->toString(), $fromSuffix)) {
                continue;
            }

            foreach ($graph->edges($node->id()) as $edge) {
                if ($edge->kind() === $kind && str_ends_with($edge->to()->toString(), $toSuffix)) {
                    $this->addToAssertionCount(1);

                    return;
                }
            }
        }

        self::fail(
            $msg."\nNodes: ".implode(', ', array_map(
                fn ($n) => $n->id()->toString(),
                $graph->nodes(),
            )),
        );
    }
}
