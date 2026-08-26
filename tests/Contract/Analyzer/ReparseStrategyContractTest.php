<?php

declare(strict_types=1);

namespace Tests\Contract\Analyzer;

use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\PhpStanAnalyzer\ReparsedSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\AnalysedSnippet;
use Tests\Fixture\Graph\GraphRelations;

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

        $graph = AnalysedSnippet::graph($code);

        GraphRelations::assertRelationExists($graph, 'Multi::alpha', 'DepA', EdgeKind::Instantiation);
        GraphRelations::assertRelationExists($graph, 'Multi::beta', 'DepB', EdgeKind::Instantiation);
        GraphRelations::assertRelationExists($graph, 'Multi::gamma', 'DepC::VAL', EdgeKind::ConstFetch);
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

        $graph = AnalysedSnippet::graph($code);

        GraphRelations::assertRelationExists($graph, 'WithClosure::run', 'Target', EdgeKind::Instantiation);
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

        $graph = AnalysedSnippet::graph($code);

        GraphRelations::assertRelationExists($graph, 'DeepNest::run', 'Nested', EdgeKind::Instantiation);
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

        $graph = AnalysedSnippet::graph($code);

        GraphRelations::assertRelationExists($graph, 'Consumer::work', 'Svc', EdgeKind::Instantiation);
        GraphRelations::assertRelationExists($graph, 'Consumer::work', 'Svc::create', EdgeKind::StaticCall);
        GraphRelations::assertRelationExists($graph, 'Consumer::work', 'Svc::FLAG', EdgeKind::ConstFetch);
        GraphRelations::assertRelationExists($graph, 'Consumer::work', 'Svc', EdgeKind::Instanceof);
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

        $graph1 = AnalysedSnippet::graph($code);
        $graph2 = AnalysedSnippet::graph($code);

        $edges1 = GraphRelations::signatures($graph1);
        $edges2 = GraphRelations::signatures($graph2);

        sort($edges1);
        sort($edges2);

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

        $graph = AnalysedSnippet::graph($code);

        GraphRelations::assertRelationExists($graph, 'SelfCaller::entry', 'SelfCaller::helper', EdgeKind::MethodCall);
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

        $graph = AnalysedSnippet::graph($code);

        GraphRelations::assertRelationExists($graph, 'PropReader::read', 'PropReader::value', EdgeKind::PropertyAccess);
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

        $graph = AnalysedSnippet::graph($code);

        GraphRelations::assertRelationExists($graph, 'StaticReader::read', 'Registry::count', EdgeKind::StaticPropertyAccess);
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

        $graph = AnalysedSnippet::graph($code);

        GraphRelations::assertRelationExists($graph, 'AllUsages::entry', 'Dep', EdgeKind::Instantiation);
        GraphRelations::assertRelationExists($graph, 'AllUsages::entry', 'Dep::create', EdgeKind::StaticCall);
        GraphRelations::assertRelationExists($graph, 'AllUsages::entry', 'Dep::FLAG', EdgeKind::ConstFetch);
        GraphRelations::assertRelationExists($graph, 'AllUsages::entry', 'Dep', EdgeKind::Instanceof);
        GraphRelations::assertRelationExists($graph, 'AllUsages::entry', 'Dep', EdgeKind::Catch);
        GraphRelations::assertRelationExists($graph, 'AllUsages::entry', 'reparse_helper', EdgeKind::FunctionCall);
        GraphRelations::assertRelationExists($graph, 'AllUsages::entry', 'AllUsages::helper', EdgeKind::MethodCall);
        GraphRelations::assertRelationExists($graph, 'AllUsages::entry', 'AllUsages::value', EdgeKind::PropertyAccess);
        GraphRelations::assertRelationExists($graph, 'AllUsages::entry', 'Dep::counter', EdgeKind::StaticPropertyAccess);
    }
}
