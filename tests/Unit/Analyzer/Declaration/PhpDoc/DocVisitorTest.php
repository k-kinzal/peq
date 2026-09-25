<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Declaration\PhpDoc;

use App\Analyzer\Declaration\PhpDoc\DocParser;
use App\Analyzer\Declaration\PhpDoc\DocScope;
use App\Analyzer\Declaration\PhpDoc\DocVisitor;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\EnumNode;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\GraphInterfaceNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\TraitNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\EnumNodeId;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Analyzer\Graph\NodeId\InterfaceNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeId\TraitNodeId;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
    public function testEnterNodeKeepsEveryConsecutiveDocComment(): void
    {
        $graph = new Graph();
        $graph->addNode(new FunctionNode(FunctionNodeId::of('run'), true));
        $names = new NameResolver();
        $visitor = new DocVisitor($graph, '/source.php', new DocParser(), $names);
        $nodes = (new ParserFactory())->createForHostVersion()->parse('<?php function run($a, $b) { /** @var First $a */ /* ordinary */ /** @var Second $b */ $a->work($b); }') ?? [];
        (new NodeTraverser($names, $visitor))->traverse($nodes);

        self::assertSame(['First', 'Second'], array_map(static fn (Edge $edge): string => $edge->to()->toString(), $graph->forwardEdges()));
    }

    public function testRecordKeepsSeparateCommentsOnTheSameLine(): void
    {
        $graph = new Graph();
        $graph->addNode(new FunctionNode(FunctionNodeId::of('run'), true));
        $names = new NameResolver();
        $visitor = new DocVisitor($graph, '/source.php', new DocParser(), $names);

        (new NodeTraverser($names, $visitor))->traverse((new ParserFactory())->createForNewestSupportedVersion()->parse('<?php function run(){ /** @var Item $a */ $a = null; /** @var Item $b */ $b = null; }') ?? []);

        self::assertSame([26, 57], array_map(static fn (Edge $edge): ?int => $edge->meta()->offset, $graph->forwardEdges()));
        self::assertNotNull($graph->nodeNamed('Item'));
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

    public function testClassesAndEnterNodeRecognizeOnlyResolvedClassLikeDeclarationsAsTypes(): void
    {
        $graph = new Graph();
        $graph->addNodes([
            new FunctionNode(FunctionNodeId::of('App\run'), true),
            new ClassNode(ClassNodeId::of('App\PHP_VERSION_ID'), true),
            new GraphInterfaceNode(InterfaceNodeId::of('App\PHP_INT_MAX'), true),
            new TraitNode(TraitNodeId::of('App\PHP_INT_MIN'), true),
            new EnumNode(EnumNodeId::of('App\PHP_OS'), true),
            new ClassNode(ClassNodeId::of('App\PHP_SAPI')),
            new FunctionNode(FunctionNodeId::of('App\PHP_BINARY'), true),
        ]);
        $source = <<<'PHP'
            <?php
            namespace App;
            /** @return PHP_VERSION_ID|PHP_INT_MAX|PHP_INT_MIN|PHP_OS|PHP_SAPI|PHP_BINARY */
            function run() {}
            PHP;
        $names = new NameResolver();
        $classes = DocVisitor::classes($graph);
        $visitor = new DocVisitor($graph, '/source.php', new DocParser(), $names, $classes);

        (new NodeTraverser($names, $visitor))->traverse((new ParserFactory())->createForNewestSupportedVersion()->parse($source) ?? []);

        self::assertSame(['app\php_version_id' => true, 'app\php_int_max' => true, 'app\php_int_min' => true, 'app\php_os' => true], $classes);
        self::assertSame(['App\PHP_VERSION_ID', 'App\PHP_INT_MAX', 'App\PHP_INT_MIN', 'App\PHP_OS'], array_map(static fn (Edge $edge): string => $edge->to()->toString(), $graph->forwardEdges()));
    }

    public function testLeaveNodeRestoresClassTemplatesAfterMethodShadowing(): void
    {
        $graph = new Graph();
        $graph->addNodes([
            new ClassNode(ClassNodeId::of('App\Subject'), true),
            new MethodNode(MethodNodeId::of('App\Subject', 'first'), true),
            new MethodNode(MethodNodeId::of('App\Subject', 'second'), true),
        ]);
        $source = <<<'PHP'
            <?php
            namespace App;
            /** @template T of Item */
            class Subject {
                /**
                 * @template T of Other
                 * @return T
                 */
                function first() {}
                /** @return T */
                function second() {}
            }
            PHP;
        $names = new NameResolver();
        $visitor = new DocVisitor($graph, '/source.php', new DocParser(), $names);

        (new NodeTraverser($names, $visitor))->traverse((new ParserFactory())->createForNewestSupportedVersion()->parse($source) ?? []);

        self::assertSame([
            ['App\Subject', 'App\Item'],
            ['App\Subject::first', 'App\Other'],
            ['App\Subject::first', 'App\Other'],
            ['App\Subject::second', 'App\Item'],
        ], array_map(static fn (Edge $edge): array => [$edge->from()->toString(), $edge->to()->toString()], $graph->forwardEdges()));
    }

    #[DataProvider('providerSourcePositions')]
    public function testRecordHandlesMissingSourcePositions(?int $tagLine, ?int $tagOffset, int $commentOffset, int $expectedLine, ?int $expectedOffset): void
    {
        $graph = new Graph();
        $graph->addNode(new FunctionNode(FunctionNodeId::of('run'), true));
        $names = new NameResolver();
        $visitor = new DocVisitor($graph, '/source.php', new DocParser(), $names);
        $function = new Function_('run');
        $function->namespacedName = new Name('run');
        $names->beforeTraverse([$function]);
        $visitor->enterNode($function);
        $tag = new PhpDocTagNode('@return', new ReturnTagValueNode(new IdentifierTypeNode('Item'), ''));
        $tag->setAttribute('startLine', $tagLine);
        $tag->setAttribute('peqOffset', $tagOffset);

        $visitor->record(new PhpDocNode([$tag]), new DocScope($names->getNameContext()), 10, $commentOffset);

        self::assertSame([
            ['run', 'Item', '/source.php', $expectedLine, 1, $expectedOffset],
        ], array_map(static fn (Edge $edge): array => [$edge->from()->toString(), $edge->to()->toString(), $edge->meta()->path, $edge->meta()->line, $edge->meta()->column, $edge->meta()->offset], $graph->forwardEdges()));
    }

    /**
     * @return iterable<string, array{?int, ?int, int, int, ?int}>
     */
    public static function providerSourcePositions(): iterable
    {
        yield 'comment starts at byte zero' => [2, 4, 0, 11, 4];

        yield 'tag has no line or offset' => [null, null, 100, 10, null];

        yield 'comment has no offset' => [2, 4, -1, 11, null];

        yield 'tag starts at byte zero' => [1, 0, 100, 10, 100];
    }
}
