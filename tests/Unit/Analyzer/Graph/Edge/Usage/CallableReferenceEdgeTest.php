<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Edge\Usage;

use App\Analyzer\Graph\Call\CallArgument;
use App\Analyzer\Graph\Call\CallSite;
use App\Analyzer\Graph\Edge\Usage\CallableReferenceEdge;
use App\Analyzer\Graph\Edge\Usage\FunctionCallEdge;
use App\Analyzer\Graph\EdgeKind;
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
#[CoversClass(CallableReferenceEdge::class)]
#[UsesNamespace('App')]
#[Small]
final class CallableReferenceEdgeTest extends TestCase
{
    public function testKindIsAReferenceRatherThanAnInvocation(): void
    {
        $source = new FunctionNode(FunctionNodeId::of('A'));
        $target = new FunctionNode(FunctionNodeId::of('B'));
        $meta = new FileMeta('/source.php', 2, 3, 20);
        $site = new CallSite($source, $source, $meta, 27, 'B($arg)', [new CallArgument('$arg')]);
        $reference = new CallableReferenceEdge(new FunctionCallEdge($source, $target, $meta), new CallSite($source, $source, $meta, 27, 'B(...)', [], true));
        self::assertSame(EdgeKind::CallableReference, $reference->kind());
        self::assertTrue($reference->site->callableReference);
        self::assertSame([], $reference->site->arguments);
        self::assertSame($reference, $reference->invert()->invert());
    }
}
