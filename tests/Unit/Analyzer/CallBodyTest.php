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
}
