<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Binding;

use App\Gql\Binding\BindingRow;
use App\Gql\Binding\BindingTable;
use App\Gql\Datum\NullDatum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BindingTable::class)]
#[UsesClass(BindingRow::class)]
#[UsesClass(NullDatum::class)]
#[Small]
final class BindingTableTest extends TestCase
{
    public function testUnitIsOneRowSoThatTheFirstClauseRunsOnce(): void
    {
        self::assertCount(1, BindingTable::unit()->rows);
    }

    public function testNothingIsATableRatherThanTheAbsenceOfOne(): void
    {
        self::assertSame([], BindingTable::nothing()->rows);
    }

    public function testATableCarriesTheRowsItWasGiven(): void
    {
        $row = BindingRow::unit();

        self::assertSame([$row], (new BindingTable([$row]))->rows);
    }

    public function testNamesAreEverythingAnyRowBinds(): void
    {
        $left = BindingRow::unit()->with('p', new NullDatum());
        $right = BindingRow::unit()->with('q', new NullDatum());

        self::assertSame(['p', 'q'], (new BindingTable([$left, $right]))->names());
    }

    public function testNamesOfATableWithNoRowsAreNone(): void
    {
        self::assertSame([], BindingTable::nothing()->names());
    }
}
