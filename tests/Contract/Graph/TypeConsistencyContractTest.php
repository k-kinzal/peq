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

/**
 * @internal
 */
#[CoversClass(EdgeKind::class)]
#[Small]
final class TypeConsistencyContractTest extends TestCase
{
    public function testEveryKindOfRelationHasExactlyOneClass(): void
    {
        $files = glob(dirname(__DIR__, 3).'/src/Analyzer/Graph/Edge/*/*Edge.php');

        self::assertIsArray($files);
        self::assertCount(count(EdgeKind::cases()), $files);
    }

    public function testEveryKindOfSymbolHasExactlyOneNodeClass(): void
    {
        $files = glob(dirname(__DIR__, 3).'/src/Analyzer/Graph/Node/*Node.php');

        self::assertIsArray($files);
        self::assertCount(count(NodeKind::cases()), $files);
    }

    public function testEveryKindOfSymbolHasExactlyOneIdentifierClass(): void
    {
        $files = glob(dirname(__DIR__, 3).'/src/Analyzer/Graph/NodeId/*NodeId.php');

        self::assertIsArray($files);
        self::assertCount(count(NodeKind::cases()), $files);
    }

    public function testOnlyTheTwoDerivedReadingsAreReadTowardsTheSubject(): void
    {
        self::assertSame(
            [EdgeKind::UsedBy, EdgeKind::DeclaredIn],
            array_values(array_filter(EdgeKind::cases(), static fn (EdgeKind $kind): bool => $kind->direction() === Direction::UsedBy)),
        );
    }

    public function testTheDerivedReadingsAreTheOnlyClassesInTheInverseGroup(): void
    {
        $files = glob(dirname(__DIR__, 3).'/src/Analyzer/Graph/Edge/Inverse/*.php');

        self::assertIsArray($files);
        self::assertSame(['DeclaredInEdge.php', 'UsedByEdge.php'], array_map('basename', $files));
    }

    #[DataProvider('providerEveryKindOfRelation')]
    public function testEveryKindOfRelationIsClassifiedByDirection(EdgeKind $kind): void
    {
        self::assertContains($kind->direction(), [Direction::Uses, Direction::UsedBy]);
    }

    /**
     * @return iterable<string, array{EdgeKind}>
     */
    public static function providerEveryKindOfRelation(): iterable
    {
        foreach (EdgeKind::cases() as $kind) {
            yield $kind->value => [$kind];
        }
    }
}
