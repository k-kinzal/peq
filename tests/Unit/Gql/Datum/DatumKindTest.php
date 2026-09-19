<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Datum;

use App\Gql\Datum\DatumKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DatumKind::class)]
#[Small]
final class DatumKindTest extends TestCase
{
    #[DataProvider('providerKindsAndTheNamesGqlGivesThem')]
    public function testTypeNameNamesTheKindTheWayGqlWritesTheType(DatumKind $kind, string $written): void
    {
        self::assertSame($written, $kind->typeName());
    }

    /**
     * @return iterable<string, array{DatumKind, string}>
     */
    public static function providerKindsAndTheNamesGqlGivesThem(): iterable
    {
        yield 'the absence of a value' => [DatumKind::Null, 'NULL'];

        yield 'a truth value' => [DatumKind::Boolean, 'BOOL'];

        yield 'a whole number' => [DatumKind::Integer, 'INT64'];

        yield 'an approximate number' => [DatumKind::Float, 'FLOAT64'];

        yield 'a character string' => [DatumKind::Text, 'STRING'];

        yield 'a list' => [DatumKind::ListOf, 'LIST'];

        yield 'a symbol' => [DatumKind::Node, 'NODE'];

        yield 'a relation' => [DatumKind::Edge, 'EDGE'];

        yield 'a path' => [DatumKind::Path, 'PATH'];

        yield 'a moment' => [DatumKind::DateTime, 'ZONED DATETIME'];
    }

    #[DataProvider('providerNumericKinds')]
    public function testNumericReportsTheKindsThatMixFreely(DatumKind $kind): void
    {
        self::assertTrue($kind->numeric());
    }

    /**
     * @return iterable<string, array{DatumKind}>
     */
    public static function providerNumericKinds(): iterable
    {
        yield 'a whole number' => [DatumKind::Integer];

        yield 'an approximate number' => [DatumKind::Float];
    }

    public function testNumericDoesNotReportAStringThatSpellsANumber(): void
    {
        self::assertFalse(DatumKind::Text->numeric());
    }

    public function testRankPutsTheAbsenceOfAValueFirstBecauseGqlSaysItIsSmallest(): void
    {
        self::assertSame(0, DatumKind::Null->rank());
    }

    public function testRankPutsBothNumbersTogetherSoThatTheySortAsNumbers(): void
    {
        self::assertSame(DatumKind::Integer->rank(), DatumKind::Float->rank());
    }

    public function testRankPutsNumbersBeforeText(): void
    {
        self::assertLessThan(DatumKind::Text->rank(), DatumKind::Integer->rank());
    }
}
