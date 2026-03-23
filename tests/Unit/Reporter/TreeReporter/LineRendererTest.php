<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\TreeReporter;

use App\Analyzer\Graph\Node\BuiltinNode;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\UnknownNode;
use App\Analyzer\Graph\NodeId\BuiltinNodeId;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\UnknownNodeId;
use App\Reporter\TreeReporter\LineRenderer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class LineRendererTest extends TestCase
{
    #[Test]
    public function testRenderRootNode(): void
    {
        $renderer = new LineRenderer();
        $node = new ClassNode(new ClassNodeId('App', 'MyClass'));
        $line = $renderer->render($node, 0, [], true, false);

        self::assertSame('App\MyClass', $line);
    }

    #[Test]
    public function testRenderFirstLevelNotLastChild(): void
    {
        $renderer = new LineRenderer();
        $node = new ClassNode(new ClassNodeId('App', 'MyClass'));
        $line = $renderer->render($node, 1, [0 => true], false, false);

        self::assertSame('├── App\MyClass', $line);
    }

    #[Test]
    public function testRenderFirstLevelLastChild(): void
    {
        $renderer = new LineRenderer();
        $node = new ClassNode(new ClassNodeId('App', 'MyClass'));
        $line = $renderer->render($node, 1, [0 => true], true, false);

        self::assertSame('└── App\MyClass', $line);
    }

    #[Test]
    public function testRenderNestedLevelWithContinuation(): void
    {
        $renderer = new LineRenderer();
        $node = new ClassNode(new ClassNodeId('App', 'MyClass'));
        $line = $renderer->render($node, 2, [1 => true], true, false);

        self::assertSame('│   └── App\MyClass', $line);
    }

    #[Test]
    public function testRenderNestedLevelWithoutContinuation(): void
    {
        $renderer = new LineRenderer();
        $node = new ClassNode(new ClassNodeId('App', 'MyClass'));
        $line = $renderer->render($node, 2, [1 => false], true, false);

        self::assertSame('    └── App\MyClass', $line);
    }

    #[Test]
    public function testRenderRecursiveNode(): void
    {
        $renderer = new LineRenderer();
        $node = new ClassNode(new ClassNodeId('App', 'MyClass'));
        $line = $renderer->render($node, 1, [], true, true);

        self::assertSame('└── App\MyClass (recursive)', $line);
    }

    #[Test]
    public function testRenderBuiltinNode(): void
    {
        $renderer = new LineRenderer();
        $node = new BuiltinNode(new BuiltinNodeId('Builtin', 'string'));
        $line = $renderer->render($node, 1, [], true, false);

        self::assertSame('└── Builtin\string (builtin)', $line);
    }

    #[Test]
    public function testRenderUnknownNode(): void
    {
        $renderer = new LineRenderer();
        $node = new UnknownNode(new UnknownNodeId('App\Unknown'));
        $line = $renderer->render($node, 1, [], true, false);

        self::assertSame('└── App\Unknown (unresolved)', $line);
    }

    #[Test]
    public function testRenderDuplicateNode(): void
    {
        $renderer = new LineRenderer();
        $node = new ClassNode(new ClassNodeId('App', 'MyClass'));
        $line = $renderer->render($node, 1, [], true, false, true);

        self::assertSame('└── App\MyClass (*)', $line);
    }
}
