<?php

declare(strict_types=1);

namespace Tests\Contract\Analyzer;

use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\Graph;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Contract: when DependencyCollector defers to InClassMethodCollector for
 * closure content inside class methods, each usage expression produces
 * exactly one edge — not zero (lost) and not two (duplicated)
 */#[CoversClass(PhpStanAnalyzer::class)]
#[Large]
final class ClosureEdgeContractTest extends TestCase
{
    /**
     * Checks that closure instantiation produces exactly one edge.
     */
    #[Test]
    public function testClosureInstantiationProducesExactlyOneEdge(): void
    {
        $code = <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace Tests\Contract\Analyzer\Closure;

            class Dep {}

            class Subject {
                public function run(): void {
                    $f = function () {
                        $x = new Dep();
                    };
                }
            }
            PHP;

        $graph = self::analyzeCode($code);

        self::assertEdgeCount($graph, 'Subject::run', 'Dep', EdgeKind::Instantiation, 1);
    }

    /**
     * Checks that closure static call produces exactly one edge.
     */
    #[Test]
    public function testClosureStaticCallProducesExactlyOneEdge(): void
    {
        $code = <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace Tests\Contract\Analyzer\Closure;

            class Dep {
                public static function make(): void {}
            }

            class Subject {
                public function run(): void {
                    $f = function () {
                        Dep::make();
                    };
                }
            }
            PHP;

        $graph = self::analyzeCode($code);

        self::assertEdgeCount($graph, 'Subject::run', 'Dep::make', EdgeKind::StaticCall, 1);
    }

    /**
     * Checks that nested closure produces exactly one edge.
     */
    #[Test]
    public function testNestedClosureProducesExactlyOneEdge(): void
    {
        $code = <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace Tests\Contract\Analyzer\Closure;

            class Dep {}

            class Subject {
                public function run(): void {
                    $f = function () {
                        $g = function () {
                            $x = new Dep();
                        };
                    };
                }
            }
            PHP;

        $graph = self::analyzeCode($code);

        self::assertEdgeCount($graph, 'Subject::run', 'Dep', EdgeKind::Instantiation, 1);
    }

    /**
     * Returns the analyze code a check needs.
     */
    public static function analyzeCode(string $phpCode): Graph
    {
        $tmpDir = sys_get_temp_dir().'/peq_closure_'.uniqid();
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
     * Returns the assert edge count a check needs.
     */
    public static function assertEdgeCount(
        Graph $graph,
        string $fromSuffix,
        string $toSuffix,
        EdgeKind $kind,
        int $expected,
    ): void {
        $count = 0;
        foreach ($graph->nodes() as $node) {
            if (!str_ends_with($node->id()->toString(), $fromSuffix)) {
                continue;
            }
            foreach ($graph->edges($node->id()) as $edge) {
                if ($edge->kind() === $kind
                    && str_ends_with($edge->to()->toString(), $toSuffix)
                ) {
                    ++$count;
                }
            }
        }

        self::assertSame(
            $expected,
            $count,
            sprintf(
                'Expected %d edge(s) [%s] from *%s to *%s, got %d',
                $expected,
                $kind->value,
                $fromSuffix,
                $toSuffix,
                $count,
            ),
        );
    }
}
