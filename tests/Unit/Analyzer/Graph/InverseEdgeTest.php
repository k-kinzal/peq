<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Inverse\DeclaredInEdge;
use App\Analyzer\Graph\Edge\Inverse\UsedByEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\InverseEdge;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DeclaredInEdge::class)]
#[CoversClass(UsedByEdge::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(Edge\Declaration\MethodEdge::class)]
#[UsesClass(Edge\Usage\MethodCallEdge::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(ClassNode::class)]
#[UsesClass(MethodNode::class)]
#[Small]
final class InverseEdgeTest extends TestCase
{
    #[DataProvider('providerEveryInverseReading')]
    public function testInvertReturnsTheEdgeItWasDerivedFrom(InverseEdge $inverse, Edge $origin): void
    {
        self::assertSame($origin, $inverse->invert());
    }

    #[DataProvider('providerEveryInverseReading')]
    public function testEndpointsAreExchangedComparedToTheOrigin(InverseEdge $inverse, Edge $origin): void
    {
        self::assertSame($origin->to()->toString(), $inverse->from()->toString());
        self::assertSame($origin->from()->toString(), $inverse->to()->toString());
    }

    #[DataProvider('providerEveryInverseReading')]
    public function testAnInverseReadingIsNeverItsOwnOrigin(InverseEdge $inverse, Edge $origin): void
    {
        self::assertNotInstanceOf(InverseEdge::class, $origin);
        self::assertNotSame($inverse->kind(), $origin->kind());
    }

    /**
     * @return iterable<string, array{InverseEdge, Edge}>
     */
    public static function providerEveryInverseReading(): iterable
    {
        $meta = new FileMeta('/project/src/Domain/Invoice.php', 12, 1);
        $usage = new Edge\Usage\MethodCallEdge(
            new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true, $meta),
            new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true, $meta),
            $meta,
        );

        yield 'used by' => [new UsedByEdge($usage), $usage];

        $declaration = new Edge\Declaration\MethodEdge(
            new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, $meta),
            new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true, $meta),
            $meta,
        );

        yield 'declared in' => [new DeclaredInEdge($declaration), $declaration];
    }
}
