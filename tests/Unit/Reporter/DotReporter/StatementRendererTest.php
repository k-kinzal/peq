<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\DotReporter;

use App\Analyzer\Graph\Edge\Declaration\MethodEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\UnknownNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeId\UnknownNodeId;
use App\Analyzer\Graph\NodeKind;
use App\Reporter\DotReporter\StatementRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(StatementRenderer::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(\App\Analyzer\Graph\EdgeKind::class)]
#[UsesClass(MethodEdge::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(UnknownNodeId::class)]
#[UsesClass(ClassNode::class)]
#[UsesClass(MethodNode::class)]
#[UsesClass(UnknownNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class StatementRendererTest extends TestCase
{
    public function testOpenNamesTheDigraphAfterTheSymbolTheWalkStartedAt(): void
    {
        self::assertSame(
            ['digraph "App\\\Domain\\\Invoice" {', '    rankdir="LR";'],
            (new StatementRenderer())->open('App\Domain\Invoice'),
        );
    }

    public function testCloseEndsTheDigraph(): void
    {
        self::assertSame('}', (new StatementRenderer())->close());
    }

    public function testNodeDeclaresTheSymbolWithTheShapeOfItsKind(): void
    {
        self::assertSame(
            '    "App\\\Domain\\\Invoice" [shape="box"];',
            (new StatementRenderer())->node(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true)),
        );
    }

    public function testEdgeDrawsTheRelationLabelledWithTheKindItIs(): void
    {
        $meta = new FileMeta('/project/src/Domain/Invoice.php', 12, 1);

        self::assertSame(
            '    "App\\\Domain\\\Invoice" -> "App\\\Domain\\\Invoice::total" [label="declaration-method"];',
            (new StatementRenderer())->edge(new MethodEdge(
                new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, $meta),
                new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true, $meta),
                $meta,
            )),
        );
    }

    public function testQuoteEscapesTheSeparatorEveryPhpNamespaceIsWrittenWith(): void
    {
        self::assertSame('"App\\\Domain\\\Invoice"', StatementRenderer::quote('App\Domain\Invoice'));
    }

    public function testQuoteEscapesAQuoteThatWouldOtherwiseEndTheString(): void
    {
        self::assertSame('"say \"what\""', StatementRenderer::quote('say "what"'));
    }

    public function testQuoteLeavesANameWithNothingToEscapeAsItIs(): void
    {
        self::assertSame('"total"', StatementRenderer::quote('total'));
    }

    #[DataProvider('providerHowEachKindIsDrawn')]
    public function testShapeDrawsEachKindOfSymbolAsWhatItIs(NodeKind $kind, string $expected): void
    {
        self::assertSame($expected, StatementRenderer::shape($kind));
    }

    /**
     * @return iterable<string, array{NodeKind, string}>
     */
    public static function providerHowEachKindIsDrawn(): iterable
    {
        yield 'a class' => [NodeKind::Klass, 'box'];

        yield 'an interface' => [NodeKind::Interface, 'component'];

        yield 'a trait' => [NodeKind::Trait, 'folder'];

        yield 'an enum' => [NodeKind::Enum, 'tab'];

        yield 'a method' => [NodeKind::Method, 'ellipse'];

        yield 'a function' => [NodeKind::Function, 'ellipse'];

        yield 'a property' => [NodeKind::Property, 'parallelogram'];

        yield 'a constant' => [NodeKind::Constant, 'note'];

        yield 'an enum case' => [NodeKind::EnumCase, 'note'];

        yield 'a builtin type' => [NodeKind::Builtin, 'plaintext'];

        yield 'an unresolved symbol' => [NodeKind::Unknown, 'octagon'];
    }

    #[DataProvider('providerEveryKind')]
    public function testShapeAnswersForEveryKindTheGraphHas(NodeKind $kind): void
    {
        self::assertNotSame('', StatementRenderer::shape($kind));
    }

    /**
     * @return iterable<string, array{NodeKind}>
     */
    public static function providerEveryKind(): iterable
    {
        foreach (NodeKind::cases() as $kind) {
            yield $kind->value => [$kind];
        }
    }
}
