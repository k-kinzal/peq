<?php

declare(strict_types=1);

namespace Tests\Contract\Analyzer;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Node;
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
final class PhpStanAnalyzerInvariantContractTest extends TestCase
{
    #[DataProvider('providerCodeVariants')]
    #[Test]
    public function testGraphInvariants(string $label, string $phpCode): void
    {
        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, $phpCode);
        $graph = (new PhpStanAnalyzer())->analyze($file);
        unlink($file);
        $edges = array_merge([], ...array_map(static fn (Node $node): array => $graph->edges($node->id()), $graph->nodes()));
        $spelled = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $edges);
        $names = array_map(static fn (Node $node): string => $node->id()->toString(), $graph->nodes());

        self::assertNotSame([], $edges, "[{$label}]");
        self::assertSame([], array_values(array_filter($edges, static fn (Edge $edge): bool => $graph->edge($edge->to(), $edge->from()) === null)), "[{$label}] relations with no reverse reading");
        self::assertSame([], array_values(array_filter($edges, static fn (Edge $edge): bool => $graph->node($edge->from()) === null || $graph->node($edge->to()) === null)), "[{$label}] relations pointing outside the graph");
        self::assertSame($names, array_values(array_unique($names)), "[{$label}] identifiers naming more than one symbol");
        self::assertSame($spelled, array_values(array_unique($spelled)), "[{$label}] relations recorded twice");
    }

    /**
     * @return Generator<string, array{string, string}>
     */
    public static function providerCodeVariants(): Generator
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
}
