<?php

declare(strict_types=1);

namespace Tests\Contract\Graph;

use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Graph\GraphModel;

/**
 * @internal
 */
#[CoversClass(EdgeKind::class)]
#[Small]
final class TypeConsistencyContractTest extends TestCase
{
    public function testEveryKindOfRelationHasExactlyOneClass(): void
    {
        self::assertCount(count(GraphModel::edgeKinds()), GraphModel::edgeClasses());
    }

    public function testEveryKindOfSymbolHasExactlyOneNodeClass(): void
    {
        self::assertCount(count(GraphModel::nodeKinds()), GraphModel::nodeClasses());
    }

    public function testEveryKindOfSymbolHasExactlyOneIdentifierClass(): void
    {
        self::assertCount(count(GraphModel::nodeKinds()), GraphModel::nodeIdClasses());
    }

    public function testOnlyTheDerivedReadingsAreMarkedAsDerived(): void
    {
        self::assertCount(2, GraphModel::inverseEdgeClasses());
    }

    public function testEveryOtherRelationClassStandsForSomethingSourceCodeWrites(): void
    {
        self::assertCount(count(GraphModel::edgeKinds()) - 2, GraphModel::authoredEdgeClasses());
    }

    /**
     * @param EdgeKind $kind The kind to classify
     */
    #[DataProvider('providerEveryKindOfRelation')]
    public function testEveryKindOfRelationIsClassifiedByDirection(EdgeKind $kind): void
    {
        self::assertContains($kind->direction(), [Direction::Uses, Direction::UsedBy]);
    }

    /**
     * @return iterable<string, array{EdgeKind}> One case per relation kind
     */
    public static function providerEveryKindOfRelation(): iterable
    {
        foreach (EdgeKind::cases() as $kind) {
            yield $kind->value => [$kind];
        }
    }

    public function testTheKindsOfSymbolAreAClosedSet(): void
    {
        self::assertSame(NodeKind::cases(), GraphModel::nodeKinds());
    }
}
