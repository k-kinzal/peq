<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\Flow;

use App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency;
use App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Inspection;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence;
use PhpParser\Node\Stmt\Function_;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNamespace('App\Analyzer\ExperimentAnalyzer')]
final class BranchesTest extends TestCase
{
    public function testReadPreservesTheGuardAfterAnEarlyReturn(): void
    {
        $source = <<<'SOURCE'
            <?php function f($flag) { if ($flag) { return 0; } $a = 1; return $a; }
            SOURCE;
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $write = array_values(array_filter($graph->nodes, static fn (Occurrence $node): bool => $node->kind === 'write'))[0];
        $guards = array_values(array_filter($graph->edges, static fn (Dependency $edge): bool => $edge->from === $write->id && $edge->kind === 'control'));
        self::assertCount(1, $guards);
        self::assertSame('falsy', $guards[0]->branch);
        self::assertSame('$flag', $graph->nodes[$guards[0]->to]->variable);
    }

    public function testReadBindsConstructorGuardsToTheValueBeforeEitherAssignment(): void
    {
        $source = "<?php function f(\$foo) {\nif (\$foo) {\n\$foo = new Foo;\n} else {\n\$foo = new Bar;\n}\nreturn \$foo;\n}";
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $guards = array_values(array_filter($graph->edges, static fn (Dependency $edge): bool => $edge->kind === 'control' && $graph->nodes[$edge->from]->kind === 'unknown' && str_starts_with($graph->nodes[$edge->from]->text, 'new ')));
        self::assertSame(['truthy', 'falsy'], array_column($guards, 'branch'));
        self::assertSame([2, 2], array_map(static fn (Dependency $edge): int => $graph->nodes[$edge->to]->line, $guards));
        $definitions = array_values(array_filter($graph->edges, static fn (Dependency $edge): bool => $edge->from === $guards[0]->to && $edge->kind === 'reaching-definition'));
        self::assertCount(1, $definitions);
        self::assertSame('parameter', $graph->nodes[$definitions[0]->to]->kind);
        self::assertNotNull($graph->inventory);
        self::assertSame(['Foo', 'Bar'], array_values(array_filter(array_column($graph->inventory->sites, 'target'), static fn (?string $target): bool => $target !== null)));
    }
}
