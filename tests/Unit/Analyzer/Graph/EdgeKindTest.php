<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph;

use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\EdgeKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(EdgeKind::class)]
#[Small]
final class EdgeKindTest extends TestCase
{
    #[DataProvider('providerAuthoredKinds')]
    public function testDirectionReadsAnAuthoredRelationAwayFromItsSubject(EdgeKind $kind): void
    {
        self::assertSame(Direction::Uses, $kind->direction());
    }

    /**
     * @return iterable<string, array{EdgeKind}>
     */
    public static function providerAuthoredKinds(): iterable
    {
        foreach (EdgeKind::cases() as $kind) {
            if ($kind !== EdgeKind::UsedBy && $kind !== EdgeKind::DeclaredIn) {
                yield $kind->value => [$kind];
            }
        }
    }

    #[DataProvider('providerDerivedKinds')]
    public function testDirectionReadsADerivedRelationTowardsItsSubject(EdgeKind $kind): void
    {
        self::assertSame(Direction::UsedBy, $kind->direction());
    }

    /**
     * @return iterable<string, array{EdgeKind}>
     */
    public static function providerDerivedKinds(): iterable
    {
        yield 'used-by' => [EdgeKind::UsedBy];

        yield 'declared-in' => [EdgeKind::DeclaredIn];
    }

    public function testDirectionIsAnsweredForEveryKindThereIs(): void
    {
        $answered = array_map(static fn (EdgeKind $kind): string => $kind->direction()->value, EdgeKind::cases());

        self::assertCount(count(EdgeKind::cases()), $answered);
    }

    public function testOnlyTwoKindsAreDerivedRatherThanWritten(): void
    {
        $derived = array_filter(
            EdgeKind::cases(),
            static fn (EdgeKind $kind): bool => $kind->direction() === Direction::UsedBy,
        );

        self::assertSame([EdgeKind::UsedBy, EdgeKind::DeclaredIn], array_values($derived));
    }

    public function testEveryKindIsSpelledExactlyOnce(): void
    {
        $values = array_map(static fn (EdgeKind $kind): string => $kind->value, EdgeKind::cases());

        self::assertSame($values, array_values(array_unique($values)));
    }
}
