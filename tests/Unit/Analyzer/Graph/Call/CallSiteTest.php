<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Call;

use App\Analyzer\Graph\Call\CallArgument;
use App\Analyzer\Graph\Call\CallSite;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CallSite::class)]
#[UsesNamespace('App')]
#[Small]
final class CallSiteTest extends TestCase
{
    public function testIdNamesTheScopeAndSourceRangeAndFactsRetainWrittenArguments(): void
    {
        $source = new FunctionNode(FunctionNodeId::of('A'));
        $target = new FunctionNode(FunctionNodeId::of('B'));
        $meta = new FileMeta('/source.php', 2, 3, 20);
        $site = new CallSite($source, $source, $meta, 27, 'B($arg)', [new CallArgument('$arg')]);
        self::assertSame('A@/source.php:20:27', $site->id());
        self::assertSame([
            'callSite' => 'A@/source.php:20:27', 'enclosingSymbol' => 'A', 'expression' => 'B($arg)',
            'endOffset' => 27, 'callableReference' => false, 'arguments' => ['$arg'],
            'argumentNames' => [null], 'argumentTypes' => [null],
        ], $site->facts());
        self::assertSame($source, $site->caller);
        self::assertSame($source, $site->owner);
        self::assertSame($meta, $site->meta);
        self::assertSame($site->id(), (new CallSite($source, $source, $meta, 27, 'B(null)', [new CallArgument('null', type: 'null')]))->id());
        self::assertNotSame($site->id(), (new CallSite($source, $source, $meta, 28, 'B($arg)', []))->id());
        self::assertNotSame($site->id(), (new CallSite($target, $source, $meta, 27, 'B($arg)', []))->id());
    }

    public function testFactsRetainNamedLiteralArgumentsAndCallableReferences(): void
    {
        $source = new FunctionNode(FunctionNodeId::of('A'));
        $site = new CallSite($source, $source, new FileMeta('/source.php', 1, 1, 0), 9, 'B(x: 42)', [new CallArgument('x: 42', 'x', type: 'int')], true);
        self::assertSame([
            'callSite' => 'A@/source.php:0:9', 'enclosingSymbol' => 'A', 'expression' => 'B(x: 42)',
            'endOffset' => 9, 'callableReference' => true, 'arguments' => ['x: 42'],
            'argumentNames' => ['x'], 'argumentTypes' => ['int'],
        ], $site->facts());
    }
}
