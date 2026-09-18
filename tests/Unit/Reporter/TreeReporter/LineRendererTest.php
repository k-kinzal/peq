<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\TreeReporter;

use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId\BuiltinNodeId;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\UnknownNodeId;
use App\Reporter\TreeReporter\LineRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(LineRenderer::class)]
#[UsesClass(BuiltinNodeId::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(UnknownNodeId::class)]
#[UsesClass(Node\BuiltinNode::class)]
#[UsesClass(Node\ClassNode::class)]
#[UsesClass(Node\UnknownNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class LineRendererTest extends TestCase
{
    public function testRenderWritesTheRootWithoutAnyPrefix(): void
    {
        self::assertSame(
            'App\Domain\Invoice',
            (new LineRenderer())->render(new Node\ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 0, [], true, false),
        );
    }

    public function testRenderJoinsALastChildWithACorner(): void
    {
        self::assertSame(
            '└── App\Domain\Invoice',
            (new LineRenderer())->render(new Node\ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 1, [], true, false),
        );
    }

    public function testRenderJoinsAChildThatHasSiblingsBelowItWithATee(): void
    {
        self::assertSame(
            '├── App\Domain\Invoice',
            (new LineRenderer())->render(new Node\ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 1, [], false, false),
        );
    }

    public function testRenderContinuesTheBranchesThatAreStillOpenAbove(): void
    {
        self::assertSame(
            '│   └── App\Domain\Invoice',
            (new LineRenderer())->render(new Node\ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 2, [1 => true], true, false),
        );
    }

    public function testRenderIndentsPastABranchThatHasClosed(): void
    {
        self::assertSame(
            '    └── App\Domain\Invoice',
            (new LineRenderer())->render(new Node\ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 2, [1 => false], true, false),
        );
    }

    public function testRenderIndentsPastABranchNothingIsKnownAbout(): void
    {
        self::assertSame(
            '    └── App\Domain\Invoice',
            (new LineRenderer())->render(new Node\ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 2, [], true, false),
        );
    }

    /**
     * @param non-empty-string $expected
     */
    #[DataProvider('providerNodesAndTheirSuffixes')]
    public function testRenderNamesWhyABranchStops(Node $node, bool $isRecursive, bool $isDuplicate, string $expected): void
    {
        self::assertStringEndsWith($expected, (new LineRenderer())->render($node, 1, [], true, $isRecursive, $isDuplicate));
    }

    /**
     * @return iterable<string, array{Node, bool, bool, non-empty-string}>
     */
    public static function providerNodesAndTheirSuffixes(): iterable
    {
        yield 'a symbol closing a cycle' => [new Node\ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), true, false, '(recursive)'];

        yield 'a symbol expanded elsewhere' => [new Node\ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), false, true, '(*)'];

        yield 'a builtin type' => [new Node\BuiltinNode(BuiltinNodeId::of('int'), true), false, false, '(builtin)'];

        yield 'an unresolved symbol' => [new Node\UnknownNode(new UnknownNodeId('App\Domain\Missing')), false, false, '(unresolved)'];

        yield 'an ordinary symbol' => [new Node\ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), false, false, 'App\Domain\Invoice'];
    }

    public function testRenderPrefersTheCycleMarkOverEveryOther(): void
    {
        self::assertStringEndsWith(
            '(recursive)',
            (new LineRenderer())->render(new Node\UnknownNode(new UnknownNodeId('App\Domain\Missing')), 1, [], true, true, true),
        );
    }
}
