<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph;

use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\Edge;
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
#[CoversClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[CoversClass(Edge\Declaration\MethodEdge::class)]
#[CoversClass(Edge\Inverse\DeclaredInEdge::class)]
#[CoversClass(Edge\Usage\MethodCallEdge::class)]
#[CoversClass(Edge\Inverse\UsedByEdge::class)]
#[UsesClass(\App\Analyzer\Graph\EdgeKind::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\ClassNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\MethodNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\Node\ClassNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\MethodNode::class)]
#[Small]
final class EdgeTest extends TestCase
{
    #[DataProvider('providerEveryAuthoredRelation')]
    public function testFromAndToNameTheTwoEndsOfTheRelation(Edge $edge, string $from, string $to): void
    {
        self::assertSame($from, $edge->from()->toString());
        self::assertSame($to, $edge->to()->toString());
    }

    #[DataProvider('providerEveryAuthoredRelation')]
    public function testToNamesTheSymbolTheRelationPointsAt(Edge $edge, string $from, string $to): void
    {
        self::assertSame($to, $edge->to()->toString());
        self::assertNotSame($from, $edge->to()->toString());
    }

    #[DataProvider('providerEveryAuthoredRelation')]
    public function testKindOfAnAuthoredRelationIsReadAwayFromItsSubject(Edge $edge, string $from, string $to): void
    {
        self::assertSame(Direction::Uses, $edge->kind()->direction());
        self::assertNotSame($from, $to);
    }

    #[DataProvider('providerEveryAuthoredRelation')]
    public function testMetaNamesWhereTheRelationIsWritten(Edge $edge, string $from, string $to): void
    {
        self::assertSame('/project/src/Domain/Invoice.php', $edge->meta()->path);
        self::assertStringStartsWith('App\Domain', $from);
        self::assertStringStartsWith('App\Domain', $to);
    }

    #[DataProvider('providerEveryAuthoredRelation')]
    public function testInvertProducesADerivedReadingOfTheSameRelation(Edge $edge, string $from, string $to): void
    {
        $inverted = $edge->invert();

        self::assertInstanceOf(InverseEdge::class, $inverted);
        self::assertSame(Direction::UsedBy, $inverted->kind()->direction());
        self::assertSame($to, $inverted->from()->toString());
        self::assertSame($from, $inverted->to()->toString());
    }

    #[DataProvider('providerEveryAuthoredRelation')]
    public function testInvertingTwiceIsTheSameAsNotInverting(Edge $edge, string $from, string $to): void
    {
        self::assertSame($edge, $edge->invert()->invert());
        self::assertSame($from, $edge->invert()->invert()->from()->toString());
        self::assertSame($to, $edge->invert()->invert()->to()->toString());
    }

    /**
     * @return iterable<string, array{Edge, string, string}>
     */
    public static function providerEveryAuthoredRelation(): iterable
    {
        yield 'a usage' => [SampleEdges::methodCall(), 'App\Domain\Invoice::total', 'App\Domain\Money::add'];

        yield 'a declaration' => [SampleEdges::methodDeclaration(), 'App\Domain\Invoice', 'App\Domain\Invoice::total'];
    }
}
