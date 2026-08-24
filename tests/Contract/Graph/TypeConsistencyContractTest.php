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
 *
 * Contract tests for the graph model taken as a whole.
 *
 * The kinds of relation and the kinds of symbol are closed sets, and each of them is
 * expected to line up with the classes that implement it: one class per relation
 * kind, one node class and one identifier class per symbol kind. These are the
 * checks that notice a kind added on one side and forgotten on the other.
 */
#[CoversClass(EdgeKind::class)]
#[Small]
final class TypeConsistencyContractTest extends TestCase
{
    /**
     * Checks that the model declares exactly one class per kind of relation.
     */
    public function testEveryKindOfRelationHasExactlyOneClass(): void
    {
        self::assertCount(count(GraphModel::edgeKinds()), GraphModel::edgeClasses());
    }

    /**
     * Checks that the model declares exactly one node class per kind of symbol.
     */
    public function testEveryKindOfSymbolHasExactlyOneNodeClass(): void
    {
        self::assertCount(count(GraphModel::nodeKinds()), GraphModel::nodeClasses());
    }

    /**
     * Checks that the model declares exactly one identifier class per kind of symbol.
     */
    public function testEveryKindOfSymbolHasExactlyOneIdentifierClass(): void
    {
        self::assertCount(count(GraphModel::nodeKinds()), GraphModel::nodeIdClasses());
    }

    /**
     * Checks that only the two derived readings are marked as derived.
     */
    public function testOnlyTheDerivedReadingsAreMarkedAsDerived(): void
    {
        self::assertCount(2, GraphModel::inverseEdgeClasses());
    }

    /**
     * Checks that every other relation class stands for something source code writes.
     */
    public function testEveryOtherRelationClassStandsForSomethingSourceCodeWrites(): void
    {
        self::assertCount(count(GraphModel::edgeKinds()) - 2, GraphModel::authoredEdgeClasses());
    }

    /**
     * Checks that every kind of relation is classified as one direction or the other.
     *
     * @param EdgeKind $kind The kind to classify
     */
    #[DataProvider('providerEveryKindOfRelation')]
    public function testEveryKindOfRelationIsClassifiedByDirection(EdgeKind $kind): void
    {
        self::assertContains($kind->direction(), [Direction::Uses, Direction::UsedBy]);
    }

    /**
     * Names every kind of relation the model declares.
     *
     * @return iterable<string, array{EdgeKind}> One case per relation kind
     */
    public static function providerEveryKindOfRelation(): iterable
    {
        foreach (EdgeKind::cases() as $kind) {
            yield $kind->value => [$kind];
        }
    }

    /**
     * Checks that the kinds of symbol and the graph's own count agree.
     */
    public function testTheKindsOfSymbolAreAClosedSet(): void
    {
        self::assertSame(NodeKind::cases(), GraphModel::nodeKinds());
    }
}
