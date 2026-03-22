<?php

declare(strict_types=1);

namespace Tests\Contract\Analyzer;

use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\Graph;
use App\Analyzer\PhpStanAnalyzer\ContainerFactory;
use App\Analyzer\PhpStanAnalyzer\PhpFileCollector;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Contract tests for InClassMethodNodeProcessor's re-parse strategy.
 * Verifies correctness, completeness, and idempotency of the approach
 * that re-parses source files to recover method body ASTs stripped by
 * PHPStan v2's CleaningVisitor.
 */
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

        $graph = self::analyzeCode($code);

        self::assertEdgeExists($graph, 'Multi::alpha', 'DepA', EdgeKind::Instantiation);
        self::assertEdgeExists($graph, 'Multi::beta', 'DepB', EdgeKind::Instantiation);
        self::assertEdgeExists($graph, 'Multi::gamma', 'DepC::VAL', EdgeKind::ConstFetch);
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

        $graph = self::analyzeCode($code);

        self::assertEdgeExists($graph, 'WithClosure::run', 'Target', EdgeKind::Instantiation);
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

        $graph = self::analyzeCode($code);

        self::assertEdgeExists($graph, 'DeepNest::run', 'Nested', EdgeKind::Instantiation);
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

        $graph = self::analyzeCode($code);

        self::assertEdgeExists($graph, 'Consumer::work', 'Svc', EdgeKind::Instantiation);
        self::assertEdgeExists($graph, 'Consumer::work', 'Svc::create', EdgeKind::StaticCall);
        self::assertEdgeExists($graph, 'Consumer::work', 'Svc::FLAG', EdgeKind::ConstFetch);
        self::assertEdgeExists($graph, 'Consumer::work', 'Svc', EdgeKind::Instanceof);
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

        $graph1 = self::analyzeCode($code);
        $graph2 = self::analyzeCode($code);

        $edges1 = self::collectEdgeSignatures($graph1);
        $edges2 = self::collectEdgeSignatures($graph2);

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

        $graph = self::analyzeCode($code);

        self::assertEdgeExists($graph, 'SelfCaller::entry', 'SelfCaller::helper', EdgeKind::MethodCall);
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

        $graph = self::analyzeCode($code);

        self::assertEdgeExists($graph, 'PropReader::read', 'PropReader::value', EdgeKind::PropertyAccess);
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

        $graph = self::analyzeCode($code);

        self::assertEdgeExists($graph, 'StaticReader::read', 'Registry::count', EdgeKind::StaticPropertyAccess);
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

        $graph = self::analyzeCode($code);

        self::assertEdgeExists($graph, 'AllUsages::entry', 'Dep', EdgeKind::Instantiation);
        self::assertEdgeExists($graph, 'AllUsages::entry', 'Dep::create', EdgeKind::StaticCall);
        self::assertEdgeExists($graph, 'AllUsages::entry', 'Dep::FLAG', EdgeKind::ConstFetch);
        self::assertEdgeExists($graph, 'AllUsages::entry', 'Dep', EdgeKind::Instanceof);
        self::assertEdgeExists($graph, 'AllUsages::entry', 'Dep', EdgeKind::Catch);
        self::assertEdgeExists($graph, 'AllUsages::entry', 'reparse_helper', EdgeKind::FunctionCall);
        self::assertEdgeExists($graph, 'AllUsages::entry', 'AllUsages::helper', EdgeKind::MethodCall);
        self::assertEdgeExists($graph, 'AllUsages::entry', 'AllUsages::value', EdgeKind::PropertyAccess);
        self::assertEdgeExists($graph, 'AllUsages::entry', 'Dep::counter', EdgeKind::StaticPropertyAccess);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private static function analyzeCode(string $phpCode): Graph
    {
        $tmpDir = sys_get_temp_dir().'/peq_reparse_'.uniqid();
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

    private function assertEdgeExists(
        Graph $graph,
        string $fromSuffix,
        string $toSuffix,
        EdgeKind $kind,
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
            "Edge not found: {$fromSuffix} --[{$kind->value}]--> {$toSuffix}"
            ."\nNodes: ".implode(', ', array_map(fn ($n) => $n->id()->toString(), $graph->nodes())),
        );
    }

    /**
     * @return list<string>
     */
    private static function collectEdgeSignatures(Graph $graph): array
    {
        $signatures = [];
        foreach ($graph->nodes() as $node) {
            foreach ($graph->edges($node->id()) as $edge) {
                $signatures[] = $edge->from()->toString().'--['.$edge->kind()->value.']-->'.$edge->to()->toString();
            }
        }

        return $signatures;
    }
}
