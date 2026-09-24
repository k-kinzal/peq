<?php

declare(strict_types=1);

namespace Tests\Contract\Analyzer;

use App\Analyzer\Graph\Edge;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PhpStanAnalyzer::class)]
#[Large]
final class DeclarationEdgeContractTest extends TestCase
{
    public function testExtendsContract(): void
    {
        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace Tests\Contract\Analyzer\Generated;

            class Dep {}

            class Subject extends Dep {}
            PHP);
        $graph = (new PhpStanAnalyzer())->analyze($file);
        unlink($file);

        self::assertContains(
            'Tests\Contract\Analyzer\Generated\Subject -[declaration-extends]-> Tests\Contract\Analyzer\Generated\Dep',
            array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges()),
        );
    }

    public function testImplementsContract(): void
    {
        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace Tests\Contract\Analyzer\Generated;

            interface DepInterface {}

            class Subject implements DepInterface {}
            PHP);
        $graph = (new PhpStanAnalyzer())->analyze($file);
        unlink($file);

        self::assertContains(
            'Tests\Contract\Analyzer\Generated\Subject -[declaration-implements]-> Tests\Contract\Analyzer\Generated\DepInterface',
            array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges()),
        );
    }

    public function testTraitUseContract(): void
    {
        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace Tests\Contract\Analyzer\Generated;

            trait DepTrait {}

            class Subject {
                use DepTrait;
            }
            PHP);
        $graph = (new PhpStanAnalyzer())->analyze($file);
        unlink($file);

        self::assertContains(
            'Tests\Contract\Analyzer\Generated\Subject -[declaration-trait-use]-> Tests\Contract\Analyzer\Generated\DepTrait',
            array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges()),
        );
    }

    #[DataProvider('providerAttributeTargets')]
    public function testAttributeContract(string $code, string $relation): void
    {
        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, $code);
        $graph = (new PhpStanAnalyzer())->analyze($file);
        unlink($file);

        self::assertContains(
            $relation,
            array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges()),
        );
    }

    /**
     * @return Generator<string, array{string, string}>
     */
    public static function providerAttributeTargets(): Generator
    {
        yield 'on a class' => [
            <<<'PHP'
                <?php
                declare(strict_types=1);
                namespace Tests\Contract\Analyzer\Generated;

                #[\Attribute]
                class DepAttr {}

                #[DepAttr]
                class Subject {}
                PHP,
            'Tests\Contract\Analyzer\Generated\Subject -[attribute]-> Tests\Contract\Analyzer\Generated\DepAttr',
        ];

        yield 'on a method' => [
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
            'Tests\Contract\Analyzer\Generated\Subject::testMethod -[attribute]-> Tests\Contract\Analyzer\Generated\DepAttr',
        ];

        yield 'on a property' => [
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
            'Tests\Contract\Analyzer\Generated\Subject::prop -[attribute]-> Tests\Contract\Analyzer\Generated\DepAttr',
        ];

        yield 'on a parameter' => [
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
            'Tests\Contract\Analyzer\Generated\Subject::testMethod -[attribute]-> Tests\Contract\Analyzer\Generated\DepAttr',
        ];

        yield 'on a constant' => [
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
            'Tests\Contract\Analyzer\Generated\Subject::FOO -[attribute]-> Tests\Contract\Analyzer\Generated\DepAttr',
        ];
    }

    #[DataProvider('providerTypeShapes')]
    public function testParameterTypeContract(string $type): void
    {
        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, <<<PHP
            <?php
            declare(strict_types=1);
            namespace Tests\\Contract\\Analyzer\\Generated;

            class Dep implements \\Stringable {
                public function __toString(): string { return ''; }
            }

            class Subject {
                public function testMethod({$type} \$x): void {}
            }
            PHP);
        $graph = (new PhpStanAnalyzer())->analyze($file);
        unlink($file);

        self::assertContains(
            'Tests\Contract\Analyzer\Generated\Subject::testMethod -[declaration-type-parameter]-> Tests\Contract\Analyzer\Generated\Dep',
            array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges()),
            "Parameter typed {$type}",
        );
    }

    #[DataProvider('providerTypeShapes')]
    public function testReturnTypeContract(string $type): void
    {
        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, <<<PHP
            <?php
            declare(strict_types=1);
            namespace Tests\\Contract\\Analyzer\\Generated;

            class Dep implements \\Stringable {
                public function __toString(): string { return ''; }
            }

            class Subject {
                public function testMethod(): {$type} { return new Dep(); }
            }
            PHP);
        $graph = (new PhpStanAnalyzer())->analyze($file);
        unlink($file);

        self::assertContains(
            'Tests\Contract\Analyzer\Generated\Subject::testMethod -[declaration-type-return]-> Tests\Contract\Analyzer\Generated\Dep',
            array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges()),
            "Return typed {$type}",
        );
    }

    #[DataProvider('providerTypeShapes')]
    public function testPropertyTypeContract(string $type): void
    {
        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, <<<PHP
            <?php
            declare(strict_types=1);
            namespace Tests\\Contract\\Analyzer\\Generated;

            class Dep implements \\Stringable {
                public function __toString(): string { return ''; }
            }

            class Subject {
                public {$type} \$prop;
            }
            PHP);
        $graph = (new PhpStanAnalyzer())->analyze($file);
        unlink($file);

        self::assertContains(
            'Tests\Contract\Analyzer\Generated\Subject::prop -[declaration-type-property]-> Tests\Contract\Analyzer\Generated\Dep',
            array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges()),
            "Property typed {$type}",
        );
    }

    /**
     * @return Generator<string, array{string}>
     */
    public static function providerTypeShapes(): Generator
    {
        yield 'simple' => ['Dep'];

        yield 'nullable' => ['?Dep'];

        yield 'union' => ['Dep|null'];

        yield 'intersection' => ['Dep&\Stringable'];
    }
}
