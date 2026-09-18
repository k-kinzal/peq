<?php

declare(strict_types=1);

namespace Tests\Contract\Analyzer;

use App\Analyzer\Graph\Edge;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PhpStanAnalyzer::class)]
#[Large]
final class UsageEdgeContractTest extends TestCase
{
    #[DataProvider('providerInstantiationContexts')]
    #[Test]
    public function testInstantiationContract(string $label, string $methodBody): void
    {
        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, <<<PHP
            <?php
            declare(strict_types=1);
            namespace Tests\\Contract\\Analyzer\\Usage;

            class Dep extends \\Exception {
                public const SOME_CONST = 1;
                public static int \$staticProp = 1;
                public static function staticMethod(): void {}
            }

            class Subject {
                public function testMethod(mixed \$y = null): mixed {
                    {$methodBody}
                    return null;
                }
            }
            PHP);
        $graph = (new PhpStanAnalyzer())->analyze($file);
        unlink($file);

        self::assertContains(
            'Tests\Contract\Analyzer\Usage\Subject::testMethod -[instantiation]-> Tests\Contract\Analyzer\Usage\Dep',
            array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->authoredEdges()),
            "Contract violated [{$label}]: Instantiation edge missing",
        );
    }

    /**
     * @return Generator<string, array{string, string}>
     */
    public static function providerInstantiationContexts(): Generator
    {
        yield 'direct' => ['direct', '$x = new Dep();'];

        yield 'in_if' => ['in_if', 'if (true) { $x = new Dep(); }'];

        yield 'in_foreach' => ['in_foreach', 'foreach ([] as $_) { $x = new Dep(); }'];

        yield 'in_try' => ['in_try', 'try { $x = new Dep(); } catch (\Exception $e) {}'];

        yield 'in_ternary' => ['in_ternary', '$x = true ? new Dep() : null;'];

        yield 'as_argument' => ['as_argument', '$x = [new Dep()];'];

        yield 'as_return' => ['as_return', 'return new Dep();'];

        yield 'in_null_coalesce' => ['in_null_coalesce', '$x = $y ?? new Dep();'];

        yield 'in_closure' => ['in_closure', '$f = function() { $x = new Dep(); };'];
    }

    #[DataProvider('providerStaticCallContexts')]
    #[Test]
    public function testStaticCallContract(string $label, string $methodBody): void
    {
        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, <<<PHP
            <?php
            declare(strict_types=1);
            namespace Tests\\Contract\\Analyzer\\Usage;

            class Dep extends \\Exception {
                public const SOME_CONST = 1;
                public static int \$staticProp = 1;
                public static function staticMethod(): void {}
            }

            class Subject {
                public function testMethod(mixed \$y = null): mixed {
                    {$methodBody}
                    return null;
                }
            }
            PHP);
        $graph = (new PhpStanAnalyzer())->analyze($file);
        unlink($file);

        self::assertContains(
            'Tests\Contract\Analyzer\Usage\Subject::testMethod -[static-call]-> Tests\Contract\Analyzer\Usage\Dep::staticMethod',
            array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->authoredEdges()),
            "Contract violated [{$label}]: StaticCall edge missing",
        );
    }

    /**
     * @return Generator<string, array{string, string}>
     */
    public static function providerStaticCallContexts(): Generator
    {
        yield 'direct' => ['direct', '$x = Dep::staticMethod();'];

        yield 'in_if' => ['in_if', 'if (true) { $x = Dep::staticMethod(); }'];

        yield 'in_foreach' => ['in_foreach', 'foreach ([] as $_) { $x = Dep::staticMethod(); }'];

        yield 'in_try' => ['in_try', 'try { $x = Dep::staticMethod(); } catch (\Exception $e) {}'];

        yield 'in_ternary' => ['in_ternary', '$x = true ? Dep::staticMethod() : null;'];

        yield 'as_argument' => ['as_argument', '$x = [Dep::staticMethod()];'];

        yield 'as_return' => ['as_return', 'return Dep::staticMethod();'];

        yield 'in_null_coalesce' => ['in_null_coalesce', '$x = $y ?? Dep::staticMethod();'];

        yield 'in_closure' => ['in_closure', '$f = function() { $x = Dep::staticMethod(); };'];
    }

    #[DataProvider('providerConstFetchContexts')]
    #[Test]
    public function testConstFetchContract(string $label, string $methodBody): void
    {
        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, <<<PHP
            <?php
            declare(strict_types=1);
            namespace Tests\\Contract\\Analyzer\\Usage;

            class Dep extends \\Exception {
                public const SOME_CONST = 1;
                public static int \$staticProp = 1;
                public static function staticMethod(): void {}
            }

            class Subject {
                public function testMethod(mixed \$y = null): mixed {
                    {$methodBody}
                    return null;
                }
            }
            PHP);
        $graph = (new PhpStanAnalyzer())->analyze($file);
        unlink($file);

        self::assertContains(
            'Tests\Contract\Analyzer\Usage\Subject::testMethod -[const-fetch]-> Tests\Contract\Analyzer\Usage\Dep::SOME_CONST',
            array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->authoredEdges()),
            "Contract violated [{$label}]: ConstFetch edge missing",
        );
    }

    /**
     * @return Generator<string, array{string, string}>
     */
    public static function providerConstFetchContexts(): Generator
    {
        yield 'direct' => ['direct', '$x = Dep::SOME_CONST;'];

        yield 'in_if' => ['in_if', 'if (true) { $x = Dep::SOME_CONST; }'];

        yield 'in_foreach' => ['in_foreach', 'foreach ([] as $_) { $x = Dep::SOME_CONST; }'];

        yield 'in_try' => ['in_try', 'try { $x = Dep::SOME_CONST; } catch (\Exception $e) {}'];

        yield 'in_ternary' => ['in_ternary', '$x = true ? Dep::SOME_CONST : null;'];

        yield 'as_argument' => ['as_argument', '$x = [Dep::SOME_CONST];'];

        yield 'as_return' => ['as_return', 'return Dep::SOME_CONST;'];

        yield 'in_null_coalesce' => ['in_null_coalesce', '$x = $y ?? Dep::SOME_CONST;'];

        yield 'in_closure' => ['in_closure', '$f = function() { $x = Dep::SOME_CONST; };'];
    }

    #[DataProvider('providerInstanceofContexts')]
    #[Test]
    public function testInstanceofContract(string $label, string $methodBody): void
    {
        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, <<<PHP
            <?php
            declare(strict_types=1);
            namespace Tests\\Contract\\Analyzer\\Usage;

            class Dep extends \\Exception {
                public const SOME_CONST = 1;
                public static int \$staticProp = 1;
                public static function staticMethod(): void {}
            }

            class Subject {
                public function testMethod(mixed \$y = null): mixed {
                    {$methodBody}
                    return null;
                }
            }
            PHP);
        $graph = (new PhpStanAnalyzer())->analyze($file);
        unlink($file);

        self::assertContains(
            'Tests\Contract\Analyzer\Usage\Subject::testMethod -[instanceof]-> Tests\Contract\Analyzer\Usage\Dep',
            array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->authoredEdges()),
            "Contract violated [{$label}]: Instanceof edge missing",
        );
    }

    /**
     * @return Generator<string, array{string, string}>
     */
    public static function providerInstanceofContexts(): Generator
    {
        yield 'direct' => ['direct', '$x = $y instanceof Dep;'];

        yield 'in_if' => ['in_if', 'if ($y instanceof Dep) {}'];

        yield 'in_foreach' => ['in_foreach', 'foreach ([] as $_) { $x = $y instanceof Dep; }'];

        yield 'in_try' => ['in_try', 'try { $x = $y instanceof Dep; } catch (\Exception $e) {}'];

        yield 'in_ternary' => ['in_ternary', '$x = ($y instanceof Dep) ? 1 : 0;'];

        yield 'as_argument' => ['as_argument', '$x = [$y instanceof Dep];'];

        yield 'as_return' => ['as_return', 'return $y instanceof Dep;'];

        yield 'in_null_coalesce' => ['in_null_coalesce', '$x = $y ?? ($y instanceof Dep);'];

        yield 'in_closure' => ['in_closure', '$f = function() use ($y) { $x = $y instanceof Dep; };'];
    }

    #[DataProvider('providerCatchContexts')]
    #[Test]
    public function testCatchContract(string $label, string $methodBody): void
    {
        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, <<<PHP
            <?php
            declare(strict_types=1);
            namespace Tests\\Contract\\Analyzer\\Usage;

            class Dep extends \\Exception {
                public const SOME_CONST = 1;
                public static int \$staticProp = 1;
                public static function staticMethod(): void {}
            }

            class Subject {
                public function testMethod(mixed \$y = null): mixed {
                    {$methodBody}
                    return null;
                }
            }
            PHP);
        $graph = (new PhpStanAnalyzer())->analyze($file);
        unlink($file);

        self::assertContains(
            'Tests\Contract\Analyzer\Usage\Subject::testMethod -[catch]-> Tests\Contract\Analyzer\Usage\Dep',
            array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->authoredEdges()),
            "Contract violated [{$label}]: Catch edge missing",
        );
    }

    /**
     * @return Generator<string, array{string, string}>
     */
    public static function providerCatchContexts(): Generator
    {
        yield 'direct' => ['direct', 'try { throw new \Exception(); } catch (Dep $e) {}'];

        yield 'in_if' => ['in_if', 'if (true) { try { throw new \Exception(); } catch (Dep $e) {} }'];

        yield 'in_foreach' => ['in_foreach', 'foreach ([] as $_) { try { throw new \Exception(); } catch (Dep $e) {} }'];

        yield 'in_try' => ['in_try', 'try { try { throw new \Exception(); } catch (Dep $e) {} } catch (\Exception $e) {}'];

        yield 'in_ternary' => ['in_ternary', '(function() { try { throw new \Exception(); } catch (Dep $e) {} return null; })();'];

        yield 'as_argument' => ['as_argument', '$x = (function() { try { throw new \Exception(); } catch (Dep $e) { return $e; } return null; })();'];

        yield 'as_return' => ['as_return', 'try { throw new \Exception(); } catch (Dep $e) { return $e; }'];

        yield 'in_null_coalesce' => ['in_null_coalesce', '$x = $y ?? (function() { try { throw new \Exception(); } catch (Dep $e) { return $e; } return null; })();'];

        yield 'in_closure' => ['in_closure', '$f = function() { try { throw new \Exception(); } catch (Dep $e) {} };'];
    }

    #[DataProvider('providerFunctionCallContexts')]
    #[Test]
    public function testFunctionCallContract(string $label, string $methodBody): void
    {
        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, <<<PHP
            <?php
            declare(strict_types=1);

            function dep_func(): mixed { return null; }

            class Subject {
                public function testMethod(mixed \$y = null): mixed {
                    {$methodBody}
                    return null;
                }
            }
            PHP);
        $graph = (new PhpStanAnalyzer())->analyze($file);
        unlink($file);

        self::assertContains(
            'Subject::testMethod -[function-call]-> dep_func',
            array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->authoredEdges()),
            "Contract violated [{$label}]: FunctionCall edge missing",
        );
    }

    /**
     * @return Generator<string, array{string, string}>
     */
    public static function providerFunctionCallContexts(): Generator
    {
        yield 'direct' => ['direct', 'dep_func();'];

        yield 'in_if' => ['in_if', 'if (true) { dep_func(); }'];

        yield 'in_foreach' => ['in_foreach', 'foreach ([] as $_) { dep_func(); }'];

        yield 'in_try' => ['in_try', 'try { dep_func(); } catch (\Exception $e) {}'];

        yield 'in_ternary' => ['in_ternary', '$x = true ? dep_func() : null;'];

        yield 'as_argument' => ['as_argument', '$x = [dep_func()];'];

        yield 'as_return' => ['as_return', 'return dep_func();'];

        yield 'in_null_coalesce' => ['in_null_coalesce', '$x = $y ?? dep_func();'];

        yield 'in_closure' => ['in_closure', '$f = function() { dep_func(); };'];
    }

    #[DataProvider('providerMethodCallContexts')]
    #[Test]
    public function testMethodCallContract(string $label, string $methodBody): void
    {
        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, <<<PHP
            <?php
            declare(strict_types=1);
            namespace Tests\\Contract\\Analyzer\\Usage;

            class Subject {
                public function helperMethod(): int { return 1; }
                public function testMethod(mixed \$y = null): mixed {
                    {$methodBody}
                    return null;
                }
            }
            PHP);
        $graph = (new PhpStanAnalyzer())->analyze($file);
        unlink($file);

        self::assertContains(
            'Tests\Contract\Analyzer\Usage\Subject::testMethod -[method-call]-> Tests\Contract\Analyzer\Usage\Subject::helperMethod',
            array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->authoredEdges()),
            "Contract violated [{$label}]: MethodCall edge missing",
        );
    }

    /**
     * @return Generator<string, array{string, string}>
     */
    public static function providerMethodCallContexts(): Generator
    {
        yield 'direct' => ['direct', '$this->helperMethod();'];

        yield 'in_if' => ['in_if', 'if (true) { $this->helperMethod(); }'];

        yield 'in_foreach' => ['in_foreach', 'foreach ([] as $_) { $this->helperMethod(); }'];

        yield 'in_try' => ['in_try', 'try { $this->helperMethod(); } catch (\Exception $e) {}'];

        yield 'in_ternary' => ['in_ternary', '$x = true ? $this->helperMethod() : null;'];

        yield 'as_argument' => ['as_argument', '$x = [$this->helperMethod()];'];

        yield 'as_return' => ['as_return', 'return $this->helperMethod();'];

        yield 'in_null_coalesce' => ['in_null_coalesce', '$x = $y ?? $this->helperMethod();'];

        yield 'in_closure' => ['in_closure', '$f = function() { $this->helperMethod(); };'];

        yield 'nullsafe' => ['nullsafe', '$this?->helperMethod();'];
    }

    #[DataProvider('providerPropertyAccessContexts')]
    #[Test]
    public function testPropertyAccessContract(string $label, string $methodBody): void
    {
        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, <<<PHP
            <?php
            declare(strict_types=1);
            namespace Tests\\Contract\\Analyzer\\Usage;

            class Subject {
                public int \$targetProp = 0;
                public function testMethod(mixed \$y = null): mixed {
                    {$methodBody}
                    return null;
                }
            }
            PHP);
        $graph = (new PhpStanAnalyzer())->analyze($file);
        unlink($file);

        self::assertContains(
            'Tests\Contract\Analyzer\Usage\Subject::testMethod -[property-access]-> Tests\Contract\Analyzer\Usage\Subject::targetProp',
            array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->authoredEdges()),
            "Contract violated [{$label}]: PropertyAccess edge missing",
        );
    }

    /**
     * @return Generator<string, array{string, string}>
     */
    public static function providerPropertyAccessContexts(): Generator
    {
        yield 'direct' => ['direct', '$x = $this->targetProp;'];

        yield 'in_if' => ['in_if', 'if (true) { $x = $this->targetProp; }'];

        yield 'in_foreach' => ['in_foreach', 'foreach ([] as $_) { $x = $this->targetProp; }'];

        yield 'in_try' => ['in_try', 'try { $x = $this->targetProp; } catch (\Exception $e) {}'];

        yield 'in_ternary' => ['in_ternary', '$x = true ? $this->targetProp : null;'];

        yield 'as_argument' => ['as_argument', '$x = [$this->targetProp];'];

        yield 'as_return' => ['as_return', 'return $this->targetProp;'];

        yield 'in_null_coalesce' => ['in_null_coalesce', '$x = $this->targetProp ?? null;'];

        yield 'in_closure' => ['in_closure', '$f = function() { $x = $this->targetProp; };'];

        yield 'nullsafe' => ['nullsafe', '$x = $this?->targetProp;'];
    }

    #[DataProvider('providerStaticPropertyAccessContexts')]
    #[Test]
    public function testStaticPropertyAccessContract(string $label, string $methodBody): void
    {
        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, <<<PHP
            <?php
            declare(strict_types=1);
            namespace Tests\\Contract\\Analyzer\\Usage;

            class Dep extends \\Exception {
                public const SOME_CONST = 1;
                public static int \$staticProp = 1;
                public static function staticMethod(): void {}
            }

            class Subject {
                public function testMethod(mixed \$y = null): mixed {
                    {$methodBody}
                    return null;
                }
            }
            PHP);
        $graph = (new PhpStanAnalyzer())->analyze($file);
        unlink($file);

        self::assertContains(
            'Tests\Contract\Analyzer\Usage\Subject::testMethod -[static-property-access]-> Tests\Contract\Analyzer\Usage\Dep::staticProp',
            array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->authoredEdges()),
            "Contract violated [{$label}]: StaticPropertyAccess edge missing",
        );
    }

    /**
     * @return Generator<string, array{string, string}>
     */
    public static function providerStaticPropertyAccessContexts(): Generator
    {
        yield 'direct' => ['direct', '$x = Dep::$staticProp;'];

        yield 'in_if' => ['in_if', 'if (true) { $x = Dep::$staticProp; }'];

        yield 'in_foreach' => ['in_foreach', 'foreach ([] as $_) { $x = Dep::$staticProp; }'];

        yield 'in_try' => ['in_try', 'try { $x = Dep::$staticProp; } catch (\Exception $e) {}'];

        yield 'in_ternary' => ['in_ternary', '$x = true ? Dep::$staticProp : null;'];

        yield 'as_argument' => ['as_argument', '$x = [Dep::$staticProp];'];

        yield 'as_return' => ['as_return', 'return Dep::$staticProp;'];

        yield 'in_null_coalesce' => ['in_null_coalesce', '$x = Dep::$staticProp ?? null;'];

        yield 'in_closure' => ['in_closure', '$f = function() { $x = Dep::$staticProp; };'];
    }

    #[Test]
    public function testMethodCallOnArbitraryObjectIsIntentionallyNotDetected(): void
    {
        $code = <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace Tests\Contract\Analyzer\Generated;

            class Other {
                public function otherMethod(): void {}
            }

            class Subject {
                public function testMethod(Other $dep): void {
                    $dep->otherMethod();
                }
            }
            PHP;

        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, $code);
        $graph = (new PhpStanAnalyzer())->analyze($file);
        unlink($file);

        self::assertNotContains(
            'Tests\Contract\Analyzer\Generated\Subject::testMethod -[method-call]-> Tests\Contract\Analyzer\Generated\Other::otherMethod',
            array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->authoredEdges()),
            'Intentional: $obj->method() is not detected (requires type inference)',
        );
    }

    #[Test]
    public function testPropertyAccessOnArbitraryObjectIsIntentionallyNotDetected(): void
    {
        $code = <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace Tests\Contract\Analyzer\Generated;

            class Other {
                public int $value = 0;
            }

            class Subject {
                public function testMethod(Other $dep): void {
                    $x = $dep->value;
                }
            }
            PHP;

        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, $code);
        $graph = (new PhpStanAnalyzer())->analyze($file);
        unlink($file);

        self::assertNotContains(
            'Tests\Contract\Analyzer\Generated\Subject::testMethod -[property-access]-> Tests\Contract\Analyzer\Generated\Other::value',
            array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->authoredEdges()),
            'Intentional: $obj->prop is not detected (requires type inference)',
        );
    }
}
