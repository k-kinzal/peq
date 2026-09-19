<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Edge\Inverse;

use App\Analyzer\Graph\Edge\Inverse\UsedByEdge;
use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(UsedByEdge::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(MethodCallEdge::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class UsedByEdgeTest extends TestCase
{
    #[DataProvider('providerTotalCallingAdd')]
    public function testFromNamesTheSymbolThatIsUsed(MethodCallEdge $usage): void
    {
        self::assertSame('App\Domain\Money::add', (new UsedByEdge($usage))->from()->toString());
    }

    #[DataProvider('providerTotalCallingAdd')]
    public function testToNamesTheSymbolThatUsesIt(MethodCallEdge $usage): void
    {
        self::assertSame('App\Domain\Invoice::total', (new UsedByEdge($usage))->to()->toString());
    }

    #[DataProvider('providerTotalCallingAdd')]
    public function testKindMarksTheRelationAsAReverseUsage(MethodCallEdge $usage): void
    {
        self::assertSame(EdgeKind::UsedBy, (new UsedByEdge($usage))->kind());
    }

    #[DataProvider('providerTotalCallingAdd')]
    public function testMetaIsWhereTheUsageItReversesIsWritten(MethodCallEdge $usage): void
    {
        self::assertSame($usage->meta(), (new UsedByEdge($usage))->meta());
    }

    #[DataProvider('providerTotalCallingAdd')]
    public function testInvertGivesBackTheExactUsageItWasDerivedFrom(MethodCallEdge $usage): void
    {
        self::assertSame($usage, (new UsedByEdge($usage))->invert());
    }

    #[DataProvider('providerTotalCallingAdd')]
    public function testTheOriginalKindSurvivesTheReverseReading(MethodCallEdge $usage): void
    {
        self::assertSame(EdgeKind::MethodCall, (new UsedByEdge($usage))->invert()->kind());
    }

    /**
     * @return iterable<string, array{MethodCallEdge}>
     */
    public static function providerTotalCallingAdd(): iterable
    {
        $meta = new FileMeta('/project/src/Domain/Invoice.php', 12, 1);

        yield 'App\Domain\Invoice::total calls App\Domain\Money::add' => [new MethodCallEdge(
            new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true, $meta),
            new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true, $meta),
            $meta,
        )];
    }
}
