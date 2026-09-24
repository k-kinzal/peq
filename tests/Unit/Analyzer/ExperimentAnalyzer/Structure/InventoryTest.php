<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\Structure;

use App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Inspection;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Slice;
use App\Analyzer\Graph\Direction;
use PhpParser\Node\Stmt\Function_;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNamespace('App\Analyzer\ExperimentAnalyzer')]
final class InventoryTest extends TestCase
{
    public function testReadRetainsBothBranchesEvenWhenOneIsUnreachable(): void
    {
        $source = '<?php function f($foo) { if (false) { $foo = new Foo; } else { $foo = new Bar; } }';
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        self::assertNotNull($graph->inventory);
        $targets = array_values(array_filter(array_column($graph->inventory->sites, 'target'), static fn (?string $target): bool => $target !== null));
        self::assertSame(['Foo', 'Bar'], $targets);
        $slice = Slice::of($graph, $graph->select(1, 'foo'), Direction::Uses, 0);
        self::assertSame(array_values($graph->inventory->sites), $slice->structure);
    }

    public function testTargetDoesNotInventTheClassOfADynamicReceiver(): void
    {
        $inventory = new \App\Analyzer\ExperimentAnalyzer\Structure\Inventory();
        self::assertNull($inventory->target(new \PhpParser\Node\Expr\MethodCall(new \PhpParser\Node\Expr\Variable('object'), 'run')));
        self::assertSame('Foo::run', $inventory->target(new \PhpParser\Node\Expr\StaticCall(new \PhpParser\Node\Name('Foo'), 'run')));
    }

    public function testReadKeepsDistinctNodesWithIdenticalSpans(): void
    {
        $source = '<?php function f() { return true; }';
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        self::assertNotNull($graph->inventory);
        self::assertSame(['Stmt_Function', 'Identifier', 'Stmt_Return', 'Expr_ConstFetch', 'Name'], array_column($graph->inventory->sites, 'syntax'));
    }

    public function testReadDistinguishesTheConditionThenBodyAndElseBody(): void
    {
        $source = "<?php function f(\$flag) {\nif (\$flag) {\nnew Foo;\n} else {\nnew Bar;\n}\n}";
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        self::assertNotNull($graph->inventory);
        $sites = array_values($graph->inventory->sites);
        $branches = array_values(array_filter($sites, static fn (\App\Analyzer\ExperimentAnalyzer\Structure\Site $site): bool => $site->syntax === 'Stmt_If'));
        self::assertCount(1, $branches);
        $children = array_values(array_filter($sites, static fn (\App\Analyzer\ExperimentAnalyzer\Structure\Site $site): bool => $site->parent === $branches[0]->source->id));
        self::assertSame(['Expr_Variable', 'Stmt_Expression', 'Stmt_Else'], array_column($children, 'syntax'));
        self::assertSame(['cond', 'stmts[0]', 'else'], array_column($children, 'role'));
        self::assertSame('$flag', $children[0]->source->variable);
        self::assertSame(2, $children[0]->source->line);
        self::assertSame(5, $children[0]->source->column);
        self::assertSame(2, $children[0]->source->endLine);
        self::assertSame('$flag', $children[0]->source->text);
        self::assertSame(30, $children[0]->start);
        self::assertSame(34, $children[0]->end);
        self::assertNull($sites[0]->parent);
        self::assertSame('callable', $sites[0]->role);
    }
}
