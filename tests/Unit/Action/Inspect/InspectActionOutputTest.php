<?php

declare(strict_types=1);

namespace Tests\Unit\Action\Inspect;

use App\Action\Inspect\InspectActionOutput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Graph\SampleGraph;
use Tests\Fixture\Graph\SampleNodes;

/**
 * @internal
 */
#[CoversClass(InspectActionOutput::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\DeclarationMethodEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\DeclaredInEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\MethodCallEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\UsedByEdge::class)]
#[UsesClass(\App\Analyzer\Graph\FileMeta::class)]
#[UsesClass(\App\Analyzer\Graph\Graph::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\ClassNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\MethodNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\Node\ClassNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class InspectActionOutputTest extends TestCase
{
    public function testTheGraphIsCarriedThroughUnchanged(): void
    {
        $graph = SampleGraph::invoice();

        self::assertSame($graph, (new InspectActionOutput($graph, SampleNodes::invoice()))->graph);
    }

    public function testTheSymbolIsCarriedAsANodeRatherThanAsAName(): void
    {
        $symbol = SampleNodes::invoice();

        self::assertSame($symbol, (new InspectActionOutput(SampleGraph::invoice(), $symbol))->symbol);
    }

    public function testTheSymbolCanBeAskedForItsIdentifierWithoutAnotherLookup(): void
    {
        $output = new InspectActionOutput(SampleGraph::invoice(), SampleNodes::invoice());

        self::assertSame('App\Domain\Invoice', $output->symbol->id()->toString());
    }
}
