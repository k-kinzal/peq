<?php

declare(strict_types=1);

namespace Tests\Contract\Analyzer;

use App\Analyzer\Graph\Graph;
use App\Analyzer\PhpStanAnalyzer\ContainerFactory;
use App\Analyzer\PhpStanAnalyzer\PhpFileCollector;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Contract\Graph\GraphInvariantAssertions;

/**
 * @internal
 *
 * Contract tests verifying that the PhpStanAnalyzer produces graphs satisfying
 * all structural invariants (bidirectionality, endpoint existence, node uniqueness,
 * no edge duplicates) across a variety of PHP code patterns
 */
final class PhpStanAnalyzerInvariantContractTest extends TestCase
{
    use GraphInvariantAssertions;

    #[DataProvider('provideCodeVariants')]
    #[Test]
    public function testGraphInvariants(string $label, string $phpCode): void
    {
        $graph = self::analyzeCode($phpCode);

        self::assertBidirectional($graph);
        self::assertEndpointsExist($graph);
        self::assertNodeUniqueness($graph);
        self::assertNoEdgeDuplicates($graph);
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function provideCodeVariants(): \Generator
    {
        yield 'simple class with one method and one instantiation' => [
            'simple',
            <<<'PHP'
                <?php
                declare(strict_types=1);
                namespace Tests\Contract\Analyzer\Generated;

                class Dep {}

                class Subject {
                    public function doWork(): void {
                        $x = new Dep();
                    }
                }
                PHP,
        ];

        yield 'complex class with multiple methods and mixed usage' => [
            'complex',
            <<<'PHP'
                <?php
                declare(strict_types=1);
                namespace Tests\Contract\Analyzer\Generated;

                class Dep extends \Exception {
                    public const SOME_CONST = 1;
                    public static int $staticProp = 0;
                    public static function staticMethod(): void {}
                }

                function dep_func(): void {}

                class Subject {
                    public int $myProp = 0;

                    public function helperMethod(): int { return 1; }

                    public function doWork(): void {
                        $x = new Dep();
                        Dep::staticMethod();
                        $c = Dep::SOME_CONST;
                        $i = $x instanceof Dep;
                        try { throw new \RuntimeException(); } catch (Dep $e) {}
                        dep_func();
                        $this->helperMethod();
                        $p = $this->myProp;
                        $sp = Dep::$staticProp;
                    }
                }
                PHP,
        ];

        yield 'class with closures containing usage expressions' => [
            'closures',
            <<<'PHP'
                <?php
                declare(strict_types=1);
                namespace Tests\Contract\Analyzer\Generated;

                class Dep {
                    public const VAL = 42;
                    public static function create(): self { return new self(); }
                }

                class Subject {
                    public int $counter = 0;

                    public function helperMethod(): void {}

                    public function doWork(): void {
                        $fn = function () {
                            $d = new Dep();
                            $v = Dep::VAL;
                            Dep::create();
                        };

                        $arrow = fn () => new Dep();

                        $nested = function () {
                            $inner = function () {
                                $this->helperMethod();
                                $x = $this->counter;
                            };
                        };
                    }
                }
                PHP,
        ];

        yield 'class with deeply nested control flow' => [
            'nested-control-flow',
            <<<'PHP'
                <?php
                declare(strict_types=1);
                namespace Tests\Contract\Analyzer\Generated;

                class Dep {
                    public const FLAG = true;
                    public static int $level = 0;
                    public static function check(): bool { return true; }
                }

                class Subject {
                    public int $state = 0;

                    public function helperMethod(): bool { return false; }

                    public function doWork(): void {
                        if (true) {
                            foreach ([] as $item) {
                                try {
                                    while (Dep::check()) {
                                        $x = new Dep();
                                        $c = Dep::FLAG;
                                        for ($i = 0; $i < Dep::$level; $i++) {
                                            $this->helperMethod();
                                            $s = $this->state;
                                            match (true) {
                                                $x instanceof Dep => null,
                                                default => null,
                                            };
                                        }
                                    }
                                } catch (Dep $e) {
                                    // nested catch
                                } catch (\Exception $e) {
                                    // fallback catch
                                }
                            }
                        }
                    }
                }
                PHP,
        ];
    }

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
}
