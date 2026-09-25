<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer;

use App\Analyzer\CallBody;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CallBody::class)]
#[Small]
final class CallBodyTest extends TestCase
{
    public function testEnterNodeDoNotBelongToTheOuterCallable(): void
    {
        $outer = new \PhpParser\Node\Expr\MethodCall(new \PhpParser\Node\Expr\Variable('port'), 'run');
        $inner = new \PhpParser\Node\Stmt\Class_('Nested', ['stmts' => [new \PhpParser\Node\Stmt\ClassMethod('method', ['stmts' => [new \PhpParser\Node\Stmt\Expression(new \PhpParser\Node\Expr\MethodCall(new \PhpParser\Node\Expr\Variable('other'), 'run'))]])]]);
        $visitor = new CallBody();

        (new \PhpParser\NodeTraverser($visitor))->traverse([new \PhpParser\Node\Stmt\Expression($outer), $inner]);
        $calls = array_values(array_filter($visitor->expressions, static fn ($node): bool => $node instanceof \PhpParser\Node\Expr\MethodCall));

        self::assertSame([$outer], $calls);
    }

    public function testEnterNodeSelectsOnlyRequestedExpressionsWithinTheCallableBoundary(): void
    {
        $call = new \PhpParser\Node\Expr\FuncCall(new \PhpParser\Node\Name('B'));
        $closure = new \PhpParser\Node\Expr\Closure(['stmts' => [new \PhpParser\Node\Stmt\Expression($call)]]);
        $nested = new \PhpParser\Node\Stmt\Function_('nested', ['stmts' => [new \PhpParser\Node\Stmt\Expression(new \PhpParser\Node\Expr\FuncCall(new \PhpParser\Node\Name('C')))]]);
        $visitor = new CallBody(static fn (\PhpParser\Node $node): bool => $node instanceof \PhpParser\Node\Expr\FuncCall);

        (new \PhpParser\NodeTraverser($visitor))->traverse([new \PhpParser\Node\Stmt\Expression($closure), $nested]);

        self::assertSame([$call], $visitor->expressions);
    }
}
