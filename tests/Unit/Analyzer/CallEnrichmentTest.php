<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer;

use App\Analyzer\CallEnrichment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CallEnrichment::class)]
#[Medium]
final class CallEnrichmentTest extends TestCase
{
    public function testOfDoesNotDuplicateSourceOccurrences(): void
    {
        $graph = (new \App\Analyzer\NativeAnalyzer\NativeAnalyzer())->analyze(dirname(__DIR__, 2).'/Fixture/Source/Dip.php');
        $before = count($graph->forwardEdges());

        $enriched = CallEnrichment::of($graph, 80300);

        self::assertSame($graph, $enriched);
        self::assertSame($before, count($enriched->forwardEdges()));
        self::assertNotNull($enriched->nodeNamed('PDO::query'));
    }

    public function testOfEnrichesDispatchEvenWhenResolvedSymbolsHaveNoReadableBody(): void
    {
        $graph = new \App\Analyzer\Graph\Graph();
        $base = new \App\Analyzer\Graph\Node\ClassNode(\App\Analyzer\Graph\NodeId\ClassNodeId::of('Base'), true);
        $child = new \App\Analyzer\Graph\Node\ClassNode(\App\Analyzer\Graph\NodeId\ClassNodeId::of('Child'), true);
        $caller = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('Controller', 'action'), true);
        $declared = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('Base', 'run'), true);
        $body = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('Child', 'run'), true);
        $meta = new \App\Analyzer\Graph\FileMeta('/missing.php', 1, 1);
        $graph->addNodes([$base, $child, $caller, $declared, $body]);
        $graph->addEdges([new \App\Analyzer\Graph\Edge\Declaration\ExtendsEdge($child, $base, $meta), new \App\Analyzer\Graph\Edge\Usage\MethodCallEdge($caller, $declared, $meta)]);

        CallEnrichment::of($graph, 80300);
        $possible = array_values(array_filter($graph->forwardEdges(), static fn ($edge): bool => $edge instanceof \App\Analyzer\Graph\Edge\Usage\PossibleCallEdge));

        self::assertSame(['Child::run'], array_map(static fn ($edge): string => $edge->to()->toString(), $possible));
    }
}
