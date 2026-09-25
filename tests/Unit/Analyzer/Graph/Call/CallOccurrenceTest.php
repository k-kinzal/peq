<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Call;

use App\Analyzer\Graph\Call\CallArgument;
use App\Analyzer\Graph\Call\CallOccurrence;
use App\Analyzer\Graph\Call\CallSite;
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
#[CoversClass(CallOccurrence::class)]
#[UsesNamespace('App')]
#[Small]
final class CallOccurrenceTest extends TestCase
{
    public function testOccurrenceKeepsItsLexicalCallerAndInvertsWithoutLosingArguments(): void
    {
        $source = new FunctionNode(FunctionNodeId::of('A'));
        $target = new FunctionNode(FunctionNodeId::of('B'));
        $meta = new FileMeta('/source.php', 2, 3, 20);
        $site = new CallSite($source, $source, $meta, 27, 'B($arg)', [new CallArgument('$arg')]);
        $container = new FunctionNode(FunctionNodeId::of('Container'));
        $original = new FunctionCallEdge($container, $target, new FileMeta('/source.php', 2, 1, 20));
        $call = new CallOccurrence($original, $site);

        self::assertSame('A', $call->from()->toString());
        self::assertSame('B', $call->to()->toString());
        self::assertSame(EdgeKind::FunctionCall, $call->kind());
        self::assertSame($meta, $call->meta());
        self::assertSame($original, $call->relation);
        self::assertSame($site, $call->site);
        self::assertSame('B', $call->invert()->from()->toString());
        self::assertSame('A', $call->invert()->to()->toString());
        self::assertSame($call, $call->invert()->invert());
    }

    public function testFromPreservesTheWrittenRelation(): void
    {
        $source = new FunctionNode(FunctionNodeId::of('A'));
        $target = new FunctionNode(FunctionNodeId::of('B'));
        $meta = new FileMeta('/source.php', 2, 3, 20);
        $site = new CallSite($source, $source, $meta, 27, 'B($arg)', [new CallArgument('$arg')]);
        $call = new CallOccurrence(new FunctionCallEdge($source, $target, $meta), $site);
        self::assertSame($source->id(), $call->from());
    }

    public function testToPreservesTheWrittenRelation(): void
    {
        $source = new FunctionNode(FunctionNodeId::of('A'));
        $target = new FunctionNode(FunctionNodeId::of('B'));
        $meta = new FileMeta('/source.php', 2, 3, 20);
        $site = new CallSite($source, $source, $meta, 27, 'B($arg)', [new CallArgument('$arg')]);
        $call = new CallOccurrence(new FunctionCallEdge($source, $target, $meta), $site);
        self::assertSame($target->id(), $call->to());
    }

    public function testKindPreservesTheWrittenRelation(): void
    {
        $source = new FunctionNode(FunctionNodeId::of('A'));
        $target = new FunctionNode(FunctionNodeId::of('B'));
        $meta = new FileMeta('/source.php', 2, 3, 20);
        $site = new CallSite($source, $source, $meta, 27, 'B($arg)', [new CallArgument('$arg')]);
        $call = new CallOccurrence(new FunctionCallEdge($source, $target, $meta), $site);
        self::assertSame(EdgeKind::FunctionCall, $call->kind());
    }

    public function testMetaPreservesTheWrittenRelation(): void
    {
        $source = new FunctionNode(FunctionNodeId::of('A'));
        $target = new FunctionNode(FunctionNodeId::of('B'));
        $meta = new FileMeta('/source.php', 2, 3, 20);
        $site = new CallSite($source, $source, $meta, 27, 'B($arg)', [new CallArgument('$arg')]);
        $call = new CallOccurrence(new FunctionCallEdge($source, $target, $meta), $site);
        self::assertSame($meta, $call->meta());
    }

    public function testInvertPreservesTheWrittenRelation(): void
    {
        $source = new FunctionNode(FunctionNodeId::of('A'));
        $target = new FunctionNode(FunctionNodeId::of('B'));
        $meta = new FileMeta('/source.php', 2, 3, 20);
        $site = new CallSite($source, $source, $meta, 27, 'B($arg)', [new CallArgument('$arg')]);
        $call = new CallOccurrence(new FunctionCallEdge($source, $target, $meta), $site);
        self::assertSame($call, $call->invert()->invert());
    }
}
