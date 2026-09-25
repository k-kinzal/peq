<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter;

use App\Analyzer\Graph\Call\CallArgument;
use App\Analyzer\Graph\Call\CallOccurrence;
use App\Analyzer\Graph\Call\CallSite;
use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\Edge\Usage\FunctionCallEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Reporter\CallOccurrences;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CallOccurrences::class)]
#[UsesNamespace('App')]
#[Small]
final class CallOccurrencesTest extends TestCase
{
    public function testBetweenPreservesDistinctSitesInBothDirectionsAndSuppressesDuplicateTargets(): void
    {
        $source = new FunctionNode(FunctionNodeId::of('A'));
        $target = new FunctionNode(FunctionNodeId::of('B'));
        $meta = new FileMeta('/source.php', 2, 3, 20);
        $site = new CallSite($source, $source, $meta, 27, 'B($arg)', [new CallArgument('$arg')]);
        $graph = new Graph();
        $call = new CallOccurrence(new FunctionCallEdge($source, $target, $meta), $site);
        $graph->addEdge($call);
        $later = new CallSite($source, $source, new FileMeta('/source.php', 2, 13, 30), 37, 'B($arg)', [new CallArgument('$arg')]);
        $graph->addEdge(new CallOccurrence(new FunctionCallEdge($source, $target, $later->meta), $later));

        self::assertSame([$site, $later], CallOccurrences::between($graph, $source->id(), $target->id(), Direction::Uses));
        self::assertSame([$site, $later], CallOccurrences::between($graph, $target->id(), $source->id(), Direction::UsedBy));
        self::assertSame([], CallOccurrences::between($graph, null, $target->id(), Direction::Uses));
        self::assertSame([], CallOccurrences::between($graph, $source->id(), $target->id(), Direction::UsedBy));
        self::assertSame([], CallOccurrences::between($graph, $source->id(), $source->id(), Direction::Uses));
        self::assertSame('function-call: B($arg) @ /source.php:2:3', CallOccurrences::label($call));
        self::assertSame('used-by: B($arg) @ /source.php:2:3', CallOccurrences::label($call->invert()));
        self::assertNull(CallOccurrences::site($call->relation));
        self::assertSame('function-call', CallOccurrences::label($call->relation));
    }

    public function testJsonKeepsRawSourceWhileTextEscapesLineBreaks(): void
    {
        $source = new FunctionNode(FunctionNodeId::of('A'));
        $target = new FunctionNode(FunctionNodeId::of('B'));
        $meta = new FileMeta('/source.php', 2, 3, 20);
        $site = new CallSite($source, $source, $meta, 27, 'B($arg)', [new CallArgument('$arg')]);
        self::assertSame([
            'id' => 'A@/source.php:20:27', 'expression' => 'B($arg)', 'arguments' => ['$arg'],
            'argumentNames' => [null], 'argumentTypes' => [null], 'file' => '/source.php',
            'line' => 2, 'column' => 3, 'offset' => 20, 'endOffset' => 27,
            'enclosingSymbol' => 'A', 'callableReference' => false,
        ], CallOccurrences::json($site));
        $multiline = new CallSite($source, $source, $meta, 40, "B(\r\n\t42)", []);
        self::assertSame('B(\r\n\t42) @ /source.php:2:3', CallOccurrences::text($multiline));
        self::assertSame("B(\r\n\t42)", CallOccurrences::json($multiline)['expression']);
    }

    public function testSiteRetainsSourceEvidence(): void
    {
        $source = new FunctionNode(FunctionNodeId::of('A'));
        $target = new FunctionNode(FunctionNodeId::of('B'));
        $meta = new FileMeta('/source.php', 2, 3, 20);
        $site = new CallSite($source, $source, $meta, 27, 'B($arg)', [new CallArgument('$arg')]);
        $call = new CallOccurrence(new FunctionCallEdge($source, $target, $meta), $site);
        self::assertSame($site, CallOccurrences::site($call));
    }

    public function testTextRetainsSourceEvidence(): void
    {
        $source = new FunctionNode(FunctionNodeId::of('A'));
        $target = new FunctionNode(FunctionNodeId::of('B'));
        $meta = new FileMeta('/source.php', 2, 3, 20);
        $site = new CallSite($source, $source, $meta, 27, 'B($arg)', [new CallArgument('$arg')]);
        $call = new CallOccurrence(new FunctionCallEdge($source, $target, $meta), $site);
        self::assertSame('B($arg) @ /source.php:2:3', CallOccurrences::text($site));
    }

    public function testLabelRetainsSourceEvidence(): void
    {
        $source = new FunctionNode(FunctionNodeId::of('A'));
        $target = new FunctionNode(FunctionNodeId::of('B'));
        $meta = new FileMeta('/source.php', 2, 3, 20);
        $site = new CallSite($source, $source, $meta, 27, 'B($arg)', [new CallArgument('$arg')]);
        $call = new CallOccurrence(new FunctionCallEdge($source, $target, $meta), $site);
        self::assertSame('function-call: B($arg) @ /source.php:2:3', CallOccurrences::label($call));
    }
}
