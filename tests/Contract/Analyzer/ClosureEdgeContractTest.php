<?php

declare(strict_types=1);

namespace Tests\Contract\Analyzer;

use App\Analyzer\Graph\Edge;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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

        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, $code);
        $graph = (new PhpStanAnalyzer())->analyze($file);
        unlink($file);
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->authoredEdges());

        self::assertSame(['Tests\Contract\Analyzer\Closure\Subject::run -[instantiation]-> Tests\Contract\Analyzer\Closure\Dep'], array_values(array_filter($relations, static fn (string $relation): bool => $relation === 'Tests\Contract\Analyzer\Closure\Subject::run -[instantiation]-> Tests\Contract\Analyzer\Closure\Dep')));
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

        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, $code);
        $graph = (new PhpStanAnalyzer())->analyze($file);
        unlink($file);
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->authoredEdges());

        self::assertSame(['Tests\Contract\Analyzer\Closure\Subject::run -[static-call]-> Tests\Contract\Analyzer\Closure\Dep::make'], array_values(array_filter($relations, static fn (string $relation): bool => $relation === 'Tests\Contract\Analyzer\Closure\Subject::run -[static-call]-> Tests\Contract\Analyzer\Closure\Dep::make')));
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

        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, $code);
        $graph = (new PhpStanAnalyzer())->analyze($file);
        unlink($file);
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->authoredEdges());

        self::assertSame(['Tests\Contract\Analyzer\Closure\Subject::run -[instantiation]-> Tests\Contract\Analyzer\Closure\Dep'], array_values(array_filter($relations, static fn (string $relation): bool => $relation === 'Tests\Contract\Analyzer\Closure\Subject::run -[instantiation]-> Tests\Contract\Analyzer\Closure\Dep')));
    }
}
