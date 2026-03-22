<?php

declare(strict_types=1);

namespace Tests\Contract\Analyzer;

use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\Graph;
use App\Analyzer\PhpStanAnalyzer\ContainerFactory;
use App\Analyzer\PhpStanAnalyzer\PhpFileCollector;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Contract tests verifying that usage edges are produced for each expression
 * pattern regardless of the surrounding control-flow context.
 *
 * 9 patterns (5 original + 4 new) x 9+ contexts.
 */
final class UsageEdgeContractTest extends TestCase
{
    // ------------------------------------------------------------------
    // Instantiation: new Dep()
    // ------------------------------------------------------------------

    #[DataProvider('provideInstantiationContexts')]
    #[Test]
    public function testInstantiationContract(string $label, string $methodBody): void
    {
        $graph = self::analyzeCode(self::wrapInMethodBody($methodBody));
        self::assertEdgeExists(
            $graph,
            'Subject::testMethod',
            'Dep',
            EdgeKind::Instantiation,
            "Contract violated [{$label}]: Instantiation edge missing",
        );
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function provideInstantiationContexts(): \Generator
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

    // ------------------------------------------------------------------
    // Static call: Dep::staticMethod()
    // ------------------------------------------------------------------

    #[DataProvider('provideStaticCallContexts')]
    #[Test]
    public function testStaticCallContract(string $label, string $methodBody): void
    {
        $graph = self::analyzeCode(self::wrapInMethodBody($methodBody));
        self::assertEdgeExists(
            $graph,
            'Subject::testMethod',
            'Dep::staticMethod',
            EdgeKind::StaticCall,
            "Contract violated [{$label}]: StaticCall edge missing",
        );
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function provideStaticCallContexts(): \Generator
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

    // ------------------------------------------------------------------
    // Const fetch: Dep::SOME_CONST
    // ------------------------------------------------------------------

    #[DataProvider('provideConstFetchContexts')]
    #[Test]
    public function testConstFetchContract(string $label, string $methodBody): void
    {
        $graph = self::analyzeCode(self::wrapInMethodBody($methodBody));
        self::assertEdgeExists(
            $graph,
            'Subject::testMethod',
            'Dep::SOME_CONST',
            EdgeKind::ConstFetch,
            "Contract violated [{$label}]: ConstFetch edge missing",
        );
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function provideConstFetchContexts(): \Generator
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

    // ------------------------------------------------------------------
    // Instanceof: $x instanceof Dep
    // ------------------------------------------------------------------

    #[DataProvider('provideInstanceofContexts')]
    #[Test]
    public function testInstanceofContract(string $label, string $methodBody): void
    {
        $graph = self::analyzeCode(self::wrapInMethodBody($methodBody));
        self::assertEdgeExists(
            $graph,
            'Subject::testMethod',
            'Dep',
            EdgeKind::Instanceof,
            "Contract violated [{$label}]: Instanceof edge missing",
        );
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function provideInstanceofContexts(): \Generator
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

    // ------------------------------------------------------------------
    // Catch: catch (Dep $e)
    // ------------------------------------------------------------------

    #[DataProvider('provideCatchContexts')]
    #[Test]
    public function testCatchContract(string $label, string $methodBody): void
    {
        $graph = self::analyzeCode(self::wrapInMethodBody($methodBody));
        self::assertEdgeExists(
            $graph,
            'Subject::testMethod',
            'Dep',
            EdgeKind::Catch,
            "Contract violated [{$label}]: Catch edge missing",
        );
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function provideCatchContexts(): \Generator
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

    // ------------------------------------------------------------------
    // Function call: dep_func()
    // ------------------------------------------------------------------

    #[DataProvider('provideFunctionCallContexts')]
    #[Test]
    public function testFunctionCallContract(string $label, string $methodBody): void
    {
        $graph = self::analyzeCode(self::wrapInFunctionCallContext($methodBody));
        self::assertEdgeExists(
            $graph,
            'Subject::testMethod',
            'dep_func',
            EdgeKind::FunctionCall,
            "Contract violated [{$label}]: FunctionCall edge missing",
        );
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function provideFunctionCallContexts(): \Generator
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

    // ------------------------------------------------------------------
    // Method call: $this->helperMethod()
    // ------------------------------------------------------------------

    #[DataProvider('provideMethodCallContexts')]
    #[Test]
    public function testMethodCallContract(string $label, string $methodBody): void
    {
        $graph = self::analyzeCode(self::wrapInMethodCallContext($methodBody));
        self::assertEdgeExists(
            $graph,
            'Subject::testMethod',
            'Subject::helperMethod',
            EdgeKind::MethodCall,
            "Contract violated [{$label}]: MethodCall edge missing",
        );
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function provideMethodCallContexts(): \Generator
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

    // ------------------------------------------------------------------
    // Property access: $this->targetProp
    // ------------------------------------------------------------------

    #[DataProvider('providePropertyAccessContexts')]
    #[Test]
    public function testPropertyAccessContract(string $label, string $methodBody): void
    {
        $graph = self::analyzeCode(self::wrapInPropertyAccessContext($methodBody));
        self::assertEdgeExists(
            $graph,
            'Subject::testMethod',
            'Subject::targetProp',
            EdgeKind::PropertyAccess,
            "Contract violated [{$label}]: PropertyAccess edge missing",
        );
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function providePropertyAccessContexts(): \Generator
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

    // ------------------------------------------------------------------
    // Static property access: Dep::$staticProp
    // ------------------------------------------------------------------

    #[DataProvider('provideStaticPropertyAccessContexts')]
    #[Test]
    public function testStaticPropertyAccessContract(string $label, string $methodBody): void
    {
        $graph = self::analyzeCode(self::wrapInMethodBody($methodBody));
        self::assertEdgeExists(
            $graph,
            'Subject::testMethod',
            'Dep::staticProp',
            EdgeKind::StaticPropertyAccess,
            "Contract violated [{$label}]: StaticPropertyAccess edge missing",
        );
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function provideStaticPropertyAccessContexts(): \Generator
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

    // ------------------------------------------------------------------
    // Non-detection: $obj->method() (intentional limitation)
    // ------------------------------------------------------------------

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

        $graph = self::analyzeCode($code);
        self::assertEdgeNotExists(
            $graph,
            'Subject::testMethod',
            'Other::otherMethod',
            EdgeKind::MethodCall,
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

        $graph = self::analyzeCode($code);
        self::assertEdgeNotExists(
            $graph,
            'Subject::testMethod',
            'Other::value',
            EdgeKind::PropertyAccess,
            'Intentional: $obj->prop is not detected (requires type inference)',
        );
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private static function analyzeCode(string $phpCode): Graph
    {
        $tmpDir = sys_get_temp_dir().'/peq_contract_'.uniqid();
        mkdir($tmpDir, 0o777, true);
        file_put_contents($tmpDir.'/Test.php', $phpCode);

        try {
            $analyzer = new PhpStanAnalyzer(new ContainerFactory(), new PhpFileCollector());

            return $analyzer->analyze($tmpDir);
        } finally {
            @unlink($tmpDir.'/Test.php');
            @rmdir($tmpDir);
        }
    }

    private static function wrapInMethodBody(string $body): string
    {
        return <<<PHP
            <?php
            declare(strict_types=1);
            namespace Tests\\Contract\\Analyzer\\Generated;

            class Dep extends \\Exception {
                public const SOME_CONST = 1;
                public static int \$staticProp = 1;
                public static function staticMethod(): void {}
            }

            class Subject {
                public function testMethod(mixed \$y = null): mixed {
                    {$body}
                    return null;
                }
            }
            PHP;
    }

    private static function wrapInFunctionCallContext(string $body): string
    {
        return <<<PHP
            <?php
            declare(strict_types=1);

            function dep_func(): mixed { return null; }

            class Subject {
                public function testMethod(mixed \$y = null): mixed {
                    {$body}
                    return null;
                }
            }
            PHP;
    }

    private static function wrapInMethodCallContext(string $body): string
    {
        return <<<PHP
            <?php
            declare(strict_types=1);
            namespace Tests\\Contract\\Analyzer\\Generated;

            class Subject {
                public function helperMethod(): int { return 1; }
                public function testMethod(mixed \$y = null): mixed {
                    {$body}
                    return null;
                }
            }
            PHP;
    }

    private static function wrapInPropertyAccessContext(string $body): string
    {
        return <<<PHP
            <?php
            declare(strict_types=1);
            namespace Tests\\Contract\\Analyzer\\Generated;

            class Subject {
                public int \$targetProp = 0;
                public function testMethod(mixed \$y = null): mixed {
                    {$body}
                    return null;
                }
            }
            PHP;
    }

    private function assertEdgeExists(
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
        self::fail($msg."\nNodes: ".implode(', ', array_map(fn ($n) => $n->id()->toString(), $graph->nodes())));
    }

    private function assertEdgeNotExists(
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
                    self::fail($msg);
                }
            }
        }
        $this->addToAssertionCount(1);
    }
}
