<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Declaration\Calls;

use App\Analyzer\CachedSyntax;
use App\Analyzer\Declaration\Calls\CallSites;
use App\Analyzer\Graph\Call\CallArgument;
use App\Analyzer\Graph\Call\CallOccurrence;
use App\Analyzer\Graph\Edge\Usage\FunctionCallEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Analyzer\SourceParser;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Stmt\Function_;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CallSites::class)]
#[UsesNamespace('App')]
#[Small]
final class CallSitesTest extends TestCase
{
    public function testOfKeepsNestedScopesAndRestoresTheCallerAfterAClosure(): void
    {
        $syntax = CachedSyntax::parse(<<<'PHP'
            <?php
            function A() {
                B('outer');
                $f = function () {
                    B('closure');
                    $g = fn () => B('arrow');
                };
                B('after');
                function nested() { B('named'); }
                $object = new class { function method() { B('method'); } };
            }
            PHP, SourceParser::forVersion(80300));
        self::assertNotNull($syntax->statements);
        self::assertInstanceOf(Function_::class, $syntax->statements[0]);
        $owner = new FunctionNode(FunctionNodeId::of('A'), true, new FileMeta('/source.php', 2, 1));
        $scopes = CallSites::of(array_values($syntax->statements[0]->stmts), $owner, '/source.php');
        $sites = array_values($scopes->sites);

        self::assertSame(['A', 'A{closure@4:10}', 'A{closure@6:14}', 'A', 'A'], array_map(static fn ($site): string => $site->caller->id()->toString(), $sites));
        self::assertSame(['B(\'outer\')', 'B(\'closure\')', 'B(\'arrow\')', 'B(\'after\')', "new class { function method() { B('method'); } }"], array_map(static fn ($site): string => $site->expression, $sites));
        self::assertCount(2, $scopes->closures);
        self::assertSame('A', $scopes->declarations[0]->from()->toString());
        self::assertSame('A{closure@4:10}', $scopes->declarations[1]->from()->toString());
        self::assertSame($owner, $sites[2]->owner);
        self::assertSame(3, $sites[0]->meta->line);
        self::assertSame(5, $sites[0]->meta->column);
    }

    public function testAttachRetainsUnlocatedRelationsAndWrapsEveryLocatedInvocation(): void
    {
        $source = new FunctionNode(FunctionNodeId::of('A'));
        $target = new FunctionNode(FunctionNodeId::of('B'));
        $syntax = CachedSyntax::parse('<?php B(42); B(...);', SourceParser::forVersion(80300));
        self::assertNotNull($syntax->statements);
        $sites = CallSites::of($syntax->statements, $source, '/source.php');
        $graph = new Graph();
        $graph->addNodes([$source, $target]);
        $graph->addEdge(new FunctionCallEdge($source, $target, new FileMeta('/source.php', 1, 1, 6, 10)));
        $graph->addEdge(new FunctionCallEdge($source, $target, new FileMeta('/source.php', 1, 1, 13, 18)));
        $unlocated = new FunctionCallEdge($source, $target, new FileMeta('/source.php', 4, 1));
        $graph->addEdge($unlocated);

        $result = CallSites::attach($graph, ['A' => $sites]);
        $edges = $result->forwardEdges();
        self::assertCount(3, $edges);
        self::assertInstanceOf(CallOccurrence::class, $edges[0]);
        self::assertSame(EdgeKind::FunctionCall, $edges[0]->kind());
        self::assertSame(7, $edges[0]->meta()->column);
        self::assertEquals([new CallArgument('42', type: 'int')], $edges[0]->site->arguments);
        self::assertSame(EdgeKind::CallableReference, $edges[1]->kind());
        self::assertSame($unlocated, $edges[2]);
        self::assertCount(3, $graph->forwardEdges());
    }

    public function testPositionRetainsSourceMetadata(): void
    {
        $owner = new FunctionNode(FunctionNodeId::of('A'));
        $sites = new CallSites($owner, '/source.php');
        $call = new FuncCall(new \PhpParser\Node\Name('B'), [new \PhpParser\Node\Arg(new \PhpParser\Node\Expr\Variable('args'), unpack: true)]);

        self::assertSame('B(...$args)', $sites->expression($call));
        self::assertEquals([new CallArgument('...$args', unpack: true)], $sites->arguments($call));
        $call->setAttribute('startLine', 1);
        self::assertSame(1, $sites->position($call)->column);
        $call->setAttribute('peqCallArguments', [new CallArgument('42'), 'invalid']);
        self::assertEquals([new CallArgument('42')], $sites->arguments($call));
    }

    public function testEnterNodeRestoresTheContainingCallScope(): void
    {
        $owner = new FunctionNode(FunctionNodeId::of('A'));
        $sites = new CallSites($owner, '/source.php');
        $closure = new \PhpParser\Node\Expr\Closure([], ['startLine' => 2, 'startFilePos' => 20, 'peqStartColumn' => 5]);
        self::assertNull($sites->enterNode($closure));
        $sites->leaveNode($closure);
        $call = new FuncCall(new \PhpParser\Node\Name('B'), [], ['startLine' => 3, 'startFilePos' => 30, 'endFilePos' => 32]);
        self::assertNull($sites->enterNode($call));
        self::assertSame('A', $sites->sites['30:32']->caller->id()->toString());
        self::assertSame('A{closure@2:5}', $sites->closures[0]->id()->toString());
    }

    public function testLeaveNodeRestoresTheContainingCallScope(): void
    {
        $owner = new FunctionNode(FunctionNodeId::of('A'));
        $sites = new CallSites($owner, '/source.php');
        $closure = new \PhpParser\Node\Expr\Closure([], ['startLine' => 2, 'startFilePos' => 20, 'peqStartColumn' => 5]);
        self::assertNull($sites->enterNode($closure));
        $sites->leaveNode($closure);
        $call = new FuncCall(new \PhpParser\Node\Name('B'), [], ['startLine' => 3, 'startFilePos' => 30, 'endFilePos' => 32]);
        self::assertNull($sites->enterNode($call));
        self::assertSame('A', $sites->sites['30:32']->caller->id()->toString());
        self::assertSame('A{closure@2:5}', $sites->closures[0]->id()->toString());
    }

    public function testExpressionPrefersOriginalSourceText(): void
    {
        $sites = new CallSites(new FunctionNode(FunctionNodeId::of('A')), '/source.php');
        $call = new FuncCall(new \PhpParser\Node\Name('B'), [], ['peqExpression' => 'B( /* comment */ )']);
        self::assertSame('B( /* comment */ )', $sites->expression($call));
    }

    public function testArgumentsRetainAnEmptyWrittenArgumentList(): void
    {
        $sites = new CallSites(new FunctionNode(FunctionNodeId::of('A')), '/source.php');
        $call = new FuncCall(new \PhpParser\Node\Name('B'), [], ['peqCallArguments' => []]);
        self::assertSame([], $sites->arguments($call));
    }
}
