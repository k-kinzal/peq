<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\DeclaredInEdge;
use App\Analyzer\Graph\Edge\UsedByEdge;
use App\Analyzer\Graph\InverseEdge;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Graph\SampleEdges;

/**
 * @internal
 */
#[CoversClass(DeclaredInEdge::class)]
#[CoversClass(UsedByEdge::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(Edge\DeclarationMethodEdge::class)]
#[UsesClass(Edge\MethodCallEdge::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\ClassNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\MethodNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\Node\ClassNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\MethodNode::class)]
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
        $usage = SampleEdges::methodCall();

        yield 'used by' => [new UsedByEdge($usage), $usage];

        $declaration = SampleEdges::methodDeclaration();

        yield 'declared in' => [new DeclaredInEdge($declaration), $declaration];
    }
}
