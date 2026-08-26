<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Edge\Inverse;

use App\Analyzer\Graph\Edge\Inverse\UsedByEdge;
use App\Analyzer\Graph\EdgeKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Graph\SampleEdges;

/**
 * @internal
 */
#[CoversClass(UsedByEdge::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\Usage\MethodCallEdge::class)]
#[UsesClass(\App\Analyzer\Graph\FileMeta::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\MethodNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\Node\MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class UsedByEdgeTest extends TestCase
{
    public function testFromNamesTheSymbolThatIsUsed(): void
    {
        self::assertSame('App\Domain\Money::add', (new UsedByEdge(SampleEdges::methodCall()))->from()->toString());
    }

    public function testToNamesTheSymbolThatUsesIt(): void
    {
        self::assertSame('App\Domain\Invoice::total', (new UsedByEdge(SampleEdges::methodCall()))->to()->toString());
    }

    public function testKindMarksTheRelationAsAReverseUsage(): void
    {
        self::assertSame(EdgeKind::UsedBy, (new UsedByEdge(SampleEdges::methodCall()))->kind());
    }

    public function testMetaIsWhereTheUsageItReversesIsWritten(): void
    {
        $usage = SampleEdges::methodCall();

        self::assertSame($usage->meta(), (new UsedByEdge($usage))->meta());
    }

    public function testInvertGivesBackTheExactUsageItWasDerivedFrom(): void
    {
        $usage = SampleEdges::methodCall();

        self::assertSame($usage, (new UsedByEdge($usage))->invert());
    }

    public function testTheOriginalKindSurvivesTheReverseReading(): void
    {
        self::assertSame(EdgeKind::MethodCall, (new UsedByEdge(SampleEdges::methodCall()))->invert()->kind());
    }
}
