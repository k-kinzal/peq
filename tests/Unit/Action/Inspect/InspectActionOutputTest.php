<?php

declare(strict_types=1);

namespace Tests\Unit\Action\Inspect;

use App\Action\Inspect\InspectActionOutput;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(InspectActionOutput::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Declaration\MethodEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Inverse\DeclaredInEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Usage\MethodCallEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Inverse\UsedByEdge::class)]
#[UsesClass(\App\Analyzer\Graph\FileMeta::class)]
#[UsesClass(Graph::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\MethodNodeId::class)]
#[UsesClass(ClassNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class InspectActionOutputTest extends TestCase
{
    public function testTheGraphIsCarriedThroughUnchanged(): void
    {
        $symbol = new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true);
        $graph = new Graph();
        $graph->addNode($symbol);

        self::assertSame($graph, (new InspectActionOutput($graph, $symbol))->graph);
    }

    public function testTheSymbolIsCarriedAsANodeRatherThanAsAName(): void
    {
        $symbol = new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true);
        $graph = new Graph();
        $graph->addNode($symbol);

        self::assertSame($symbol, (new InspectActionOutput($graph, $symbol))->symbol);
    }

    public function testTheSymbolCanBeAskedForItsIdentifierWithoutAnotherLookup(): void
    {
        $symbol = new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true);
        $graph = new Graph();
        $graph->addNode($symbol);
        $output = new InspectActionOutput($graph, $symbol);

        self::assertSame('App\Domain\Invoice', $output->symbol->id()->toString());
    }
}
