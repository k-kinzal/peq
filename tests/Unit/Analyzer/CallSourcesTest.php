<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer;

use App\Analyzer\CallSources;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CallSources::class)]
#[Small]
final class CallSourcesTest extends TestCase
{
    public function testCallableFindsTheDeclaredBodyRatherThanAnotherMethodInTheFile(): void
    {
        $file = dirname(__DIR__, 2).'/Fixture/Source/Dip.php';
        $source = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('Tests\Fixture\Source\Dip\Controller', 'action'), true, new \App\Analyzer\Graph\FileMeta($file, 52, 1));

        $body = (new CallSources(80300))->callable($source);

        self::assertInstanceOf(\PhpParser\Node\Stmt\ClassMethod::class, $body);
        self::assertSame('action', $body->name->toString());
        self::assertCount(2, $body->getStmts() ?? []);
    }

    public function testReadTreatsAnUnavailableFileAsHavingNoBodies(): void
    {
        self::assertSame([], (new CallSources(80300))->read('/peq-file-that-does-not-exist.php'));
    }
}
