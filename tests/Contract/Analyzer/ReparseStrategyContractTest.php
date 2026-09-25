<?php

declare(strict_types=1);

namespace Tests\Contract\Analyzer;

use App\Analyzer\Graph\Edge;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use App\Analyzer\PhpStanAnalyzer\ReparsedSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ReparsedSource::class)]
#[Large]
final class ReparseStrategyContractTest extends TestCase
{
    #[Test]
    public function testMultiMethodCompleteness(): void
    {
        $code = <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace Tests\Contract\Analyzer\Reparse;

            class DepA {}
            class DepB {}
            class DepC { public const VAL = 1; }

            class Multi {
                public function alpha(): void {
                    $x = new DepA();
                }
                public function beta(): void {
                    $x = new DepB();
                }
                public function gamma(): void {
                    $x = DepC::VAL;
                }
            }
            PHP;

        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, $code);
        $graph = (new PhpStanAnalyzer())->analyze($file);
        unlink($file);
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges());

        self::assertContains('Tests\Contract\Analyzer\Reparse\Multi::alpha -[instantiation]-> Tests\Contract\Analyzer\Reparse\DepA', $relations);
        self::assertContains('Tests\Contract\Analyzer\Reparse\Multi::beta -[instantiation]-> Tests\Contract\Analyzer\Reparse\DepB', $relations);
        self::assertContains('Tests\Contract\Analyzer\Reparse\Multi::gamma -[const-fetch]-> Tests\Contract\Analyzer\Reparse\DepC::VAL', $relations);
    }

    #[Test]
    public function testClosureBodyCoverage(): void
    {
        $code = <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace Tests\Contract\Analyzer\Reparse;

            class Target {}

            class WithClosure {
                public function run(): void {
                    $f = function () {
                        $x = new Target();
                    };
                }
            }
            PHP;

        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, $code);
        $graph = (new PhpStanAnalyzer())->analyze($file);
        unlink($file);
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges());

        self::assertContains('Tests\Contract\Analyzer\Reparse\WithClosure::run{closure@9:14} -[instantiation]-> Tests\Contract\Analyzer\Reparse\Target', $relations);
    }

    #[Test]
    public function testDeeplyNestedStructures(): void
    {
        $code = <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace Tests\Contract\Analyzer\Reparse;

            class Nested {}

            class DeepNest {
                public function run(): void {
                    try {
                        foreach ([1] as $v) {
                            if ($v > 0) {
                                $x = new Nested();
                            }
                        }
                    } catch (\Exception $e) {}
                }
            }
            PHP;

        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, $code);
        $graph = (new PhpStanAnalyzer())->analyze($file);
        unlink($file);
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges());

        self::assertContains('Tests\Contract\Analyzer\Reparse\DeepNest::run -[instantiation]-> Tests\Contract\Analyzer\Reparse\Nested', $relations);
    }

    #[Test]
    public function testMixedDependencyKindsInSingleMethod(): void
    {
        $code = <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace Tests\Contract\Analyzer\Reparse;

            class Svc {
                public const FLAG = true;
                public static function create(): self { return new self(); }
            }

            class Consumer {
                public function work(): void {
                    $x = new Svc();
                    $y = Svc::create();
                    $z = Svc::FLAG;
                    if ($x instanceof Svc) {}
                }
            }
            PHP;

        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, $code);
        $graph = (new PhpStanAnalyzer())->analyze($file);
        unlink($file);
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges());

        self::assertContains('Tests\Contract\Analyzer\Reparse\Consumer::work -[instantiation]-> Tests\Contract\Analyzer\Reparse\Svc', $relations);
        self::assertContains('Tests\Contract\Analyzer\Reparse\Consumer::work -[static-call]-> Tests\Contract\Analyzer\Reparse\Svc::create', $relations);
        self::assertContains('Tests\Contract\Analyzer\Reparse\Consumer::work -[const-fetch]-> Tests\Contract\Analyzer\Reparse\Svc::FLAG', $relations);
        self::assertContains('Tests\Contract\Analyzer\Reparse\Consumer::work -[instanceof]-> Tests\Contract\Analyzer\Reparse\Svc', $relations);
    }

    #[Test]
    public function testDeterminism(): void
    {
        $code = <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace Tests\Contract\Analyzer\Reparse;

            class Dep {}

            class Deterministic {
                public function doWork(): void {
                    $a = new Dep();
                }
            }
            PHP;

        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, $code);
        $graph1 = (new PhpStanAnalyzer())->analyze($file);
        unlink($file);
        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, $code);
        $graph2 = (new PhpStanAnalyzer())->analyze($file);
        unlink($file);
        $edges1 = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph1->forwardEdges());
        $edges2 = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph2->forwardEdges());
        sort($edges1);
        sort($edges2);

        self::assertSame([
            'Tests\Contract\Analyzer\Reparse\Deterministic -[declaration-method]-> Tests\Contract\Analyzer\Reparse\Deterministic::doWork',
            'Tests\Contract\Analyzer\Reparse\Deterministic::doWork -[instantiation]-> Tests\Contract\Analyzer\Reparse\Dep',
        ], $edges1);
        self::assertSame($edges1, $edges2, 'Two analyses of the same code must produce identical edge sets');
    }

    #[Test]
    public function testMethodCallViaThisIsDetected(): void
    {
        $code = <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace Tests\Contract\Analyzer\Reparse;

            class SelfCaller {
                public function helper(): int { return 1; }
                public function entry(): void {
                    $x = $this->helper();
                }
            }
            PHP;

        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, $code);
        $graph = (new PhpStanAnalyzer())->analyze($file);
        unlink($file);
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges());

        self::assertContains('Tests\Contract\Analyzer\Reparse\SelfCaller::entry -[method-call]-> Tests\Contract\Analyzer\Reparse\SelfCaller::helper', $relations);
    }

    #[Test]
    public function testPropertyAccessViaThisIsDetected(): void
    {
        $code = <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace Tests\Contract\Analyzer\Reparse;

            class PropReader {
                public int $value = 0;
                public function read(): int {
                    return $this->value;
                }
            }
            PHP;

        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, $code);
        $graph = (new PhpStanAnalyzer())->analyze($file);
        unlink($file);
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges());

        self::assertContains('Tests\Contract\Analyzer\Reparse\PropReader::read -[property-access]-> Tests\Contract\Analyzer\Reparse\PropReader::value', $relations);
    }

    #[Test]
    public function testStaticPropertyAccessIsDetected(): void
    {
        $code = <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace Tests\Contract\Analyzer\Reparse;

            class Registry {
                public static int $count = 0;
            }

            class StaticReader {
                public function read(): int {
                    return Registry::$count;
                }
            }
            PHP;

        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, $code);
        $graph = (new PhpStanAnalyzer())->analyze($file);
        unlink($file);
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges());

        self::assertContains('Tests\Contract\Analyzer\Reparse\StaticReader::read -[static-property-access]-> Tests\Contract\Analyzer\Reparse\Registry::count', $relations);
    }

    #[Test]
    public function testAllUsageEdgeKindsDetectedInSingleMethod(): void
    {
        $code = <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace Tests\Contract\Analyzer\Reparse;

            class Dep extends \Exception {
                public const FLAG = true;
                public static int $counter = 0;
                public static function create(): self { return new self(); }
            }

            function reparse_helper(): void {}

            class AllUsages {
                public int $value = 0;
                public function helper(): int { return 1; }
                public function entry(): void {
                    $x = new Dep();
                    $y = Dep::create();
                    $z = Dep::FLAG;
                    if ($x instanceof Dep) {}
                    try { throw $x; } catch (Dep $e) {}
                    reparse_helper();
                    $this->helper();
                    $a = $this->value;
                    $b = Dep::$counter;
                }
            }
            PHP;

        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, $code);
        $graph = (new PhpStanAnalyzer())->analyze($file);
        unlink($file);
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges());

        self::assertContains('Tests\Contract\Analyzer\Reparse\AllUsages::entry -[instantiation]-> Tests\Contract\Analyzer\Reparse\Dep', $relations);
        self::assertContains('Tests\Contract\Analyzer\Reparse\AllUsages::entry -[static-call]-> Tests\Contract\Analyzer\Reparse\Dep::create', $relations);
        self::assertContains('Tests\Contract\Analyzer\Reparse\AllUsages::entry -[const-fetch]-> Tests\Contract\Analyzer\Reparse\Dep::FLAG', $relations);
        self::assertContains('Tests\Contract\Analyzer\Reparse\AllUsages::entry -[instanceof]-> Tests\Contract\Analyzer\Reparse\Dep', $relations);
        self::assertContains('Tests\Contract\Analyzer\Reparse\AllUsages::entry -[catch]-> Tests\Contract\Analyzer\Reparse\Dep', $relations);
        self::assertContains('Tests\Contract\Analyzer\Reparse\AllUsages::entry -[function-call]-> Tests\Contract\Analyzer\Reparse\reparse_helper', $relations);
        self::assertContains('Tests\Contract\Analyzer\Reparse\AllUsages::entry -[method-call]-> Tests\Contract\Analyzer\Reparse\AllUsages::helper', $relations);
        self::assertContains('Tests\Contract\Analyzer\Reparse\AllUsages::entry -[property-access]-> Tests\Contract\Analyzer\Reparse\AllUsages::value', $relations);
        self::assertContains('Tests\Contract\Analyzer\Reparse\AllUsages::entry -[static-property-access]-> Tests\Contract\Analyzer\Reparse\Dep::counter', $relations);
    }
}
