<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Declaration\PhpDoc;

use App\Analyzer\Declaration\PhpDoc\DocParser;
use App\Analyzer\Declaration\PhpDoc\DocVisitor;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DocVisitor::class)]
#[UsesNamespace('App\Analyzer')]
#[Small]
final class DocVisitorTest extends TestCase
{
    public function testRecordKeepsSeparateCommentsOnTheSameLine(): void
    {
        $graph = new Graph();
        $graph->addNode(new FunctionNode(FunctionNodeId::of('run'), true));
        $names = new NameResolver();
        $visitor = new DocVisitor($graph, '/source.php', new DocParser(), $names);

        (new NodeTraverser($names, $visitor))->traverse((new ParserFactory())->createForNewestSupportedVersion()->parse('<?php function run(){ /** @var Item $a */ $a = null; /** @var Item $b */ $b = null; }') ?? []);

        self::assertSame([26, 57], array_map(static fn (Edge $edge): ?int => $edge->meta()->offset, $graph->forwardEdges()));
    }

    public function testRecordKeepsSeparateTagOccurrencesOfTheSameDependency(): void
    {
        $graph = new Graph();
        $graph->addNode(new FunctionNode(FunctionNodeId::of('run'), true));
        $names = new NameResolver();
        $visitor = new DocVisitor($graph, '/source.php', new DocParser(), $names);

        (new NodeTraverser($names, $visitor))->traverse((new ParserFactory())->createForNewestSupportedVersion()->parse("<?php\n/**\n * @param Item \$value\n * @return Item\n */\nfunction run(\$value) {}") ?? []);

        self::assertSame([3, 4], array_map(static fn (Edge $edge): int => $edge->meta()->line, $graph->forwardEdges()));
    }

    public function testEnterNodeDoesNotCreateSymbolsForUnanalysedDeclarations(): void
    {
        $graph = new Graph();
        $names = new NameResolver();
        $visitor = new DocVisitor($graph, '/source.php', new DocParser(), $names);

        (new NodeTraverser($names, $visitor))->traverse((new ParserFactory())->createForNewestSupportedVersion()->parse('<?php /** @return Item */ function excluded() {}') ?? []);

        self::assertSame([], $graph->nodes());
        self::assertSame([], $graph->forwardEdges());
    }

    public function testLeaveNodeRestoresTheOuterOwnerAfterNestedScopes(): void
    {
        $graph = new Graph();
        $graph->addNodes([new FunctionNode(FunctionNodeId::of('App\outer'), true), new FunctionNode(FunctionNodeId::of('App\inner'), true)]);
        $source = <<<'PHP'
            <?php
            namespace App;
            /** @param Item $input */
            function outer($input) {
                /** @return Nested */
                function inner() {}
                /** @var Local $value */
                $value = null;
            }
            PHP;
        $names = new NameResolver();
        $visitor = new DocVisitor($graph, '/source.php', new DocParser(), $names);

        (new NodeTraverser($names, $visitor))->traverse((new ParserFactory())->createForNewestSupportedVersion()->parse($source) ?? []);
        $edges = array_map(static fn (Edge $edge): array => [$edge->from()->toString(), $edge->to()->toString(), $edge->meta()->line], $graph->forwardEdges());

        self::assertSame([
            ['App\outer', 'App\Item', 3],
            ['App\outer', 'App\Local', 7],
            ['App\inner', 'App\Nested', 5],
        ], $edges);
    }
}
