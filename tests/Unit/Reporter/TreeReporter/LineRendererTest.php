<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\TreeReporter;

use App\Analyzer\Graph\Node;
use App\Reporter\TreeReporter\LineRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Graph\SampleNodes;

/**
 * @internal
 */
#[CoversClass(LineRenderer::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\BuiltinNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\ClassNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\UnknownNodeId::class)]
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
            (new LineRenderer())->render(SampleNodes::invoice(), 0, [], true, false),
        );
    }

    public function testRenderJoinsALastChildWithACorner(): void
    {
        self::assertSame(
            '└── App\Domain\Invoice',
            (new LineRenderer())->render(SampleNodes::invoice(), 1, [], true, false),
        );
    }

    public function testRenderJoinsAChildThatHasSiblingsBelowItWithATee(): void
    {
        self::assertSame(
            '├── App\Domain\Invoice',
            (new LineRenderer())->render(SampleNodes::invoice(), 1, [], false, false),
        );
    }

    public function testRenderContinuesTheBranchesThatAreStillOpenAbove(): void
    {
        self::assertSame(
            '│   └── App\Domain\Invoice',
            (new LineRenderer())->render(SampleNodes::invoice(), 2, [1 => true], true, false),
        );
    }

    public function testRenderIndentsPastABranchThatHasClosed(): void
    {
        self::assertSame(
            '    └── App\Domain\Invoice',
            (new LineRenderer())->render(SampleNodes::invoice(), 2, [1 => false], true, false),
        );
    }

    public function testRenderIndentsPastABranchNothingIsKnownAbout(): void
    {
        self::assertSame(
            '    └── App\Domain\Invoice',
            (new LineRenderer())->render(SampleNodes::invoice(), 2, [], true, false),
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
        yield 'a symbol closing a cycle' => [SampleNodes::invoice(), true, false, '(recursive)'];

        yield 'a symbol expanded elsewhere' => [SampleNodes::invoice(), false, true, '(*)'];

        yield 'a builtin type' => [SampleNodes::builtin(), false, false, '(builtin)'];

        yield 'an unresolved symbol' => [SampleNodes::unresolved(), false, false, '(unresolved)'];

        yield 'an ordinary symbol' => [SampleNodes::invoice(), false, false, 'App\Domain\Invoice'];
    }

    public function testRenderPrefersTheCycleMarkOverEveryOther(): void
    {
        self::assertStringEndsWith(
            '(recursive)',
            (new LineRenderer())->render(SampleNodes::unresolved(), 1, [], true, true, true),
        );
    }
}
