<?php

declare(strict_types=1);

namespace Tests\Contract\Analyzer;

use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\AnalysedSnippet;
use Tests\Fixture\Graph\GraphRelations;

/**
 * @internal
 */
#[CoversClass(PhpStanAnalyzer::class)]
#[Large]
final class ClosureEdgeContractTest extends TestCase
{
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

        $graph = AnalysedSnippet::graph($code);

        GraphRelations::assertRelationCount($graph, 'Subject::run', 'Dep', EdgeKind::Instantiation, 1);
    }

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

        $graph = AnalysedSnippet::graph($code);

        GraphRelations::assertRelationCount($graph, 'Subject::run', 'Dep::make', EdgeKind::StaticCall, 1);
    }

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

        $graph = AnalysedSnippet::graph($code);

        GraphRelations::assertRelationCount($graph, 'Subject::run', 'Dep', EdgeKind::Instantiation, 1);
    }
}
