<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Binding;

use App\Gql\Binding\BindingRow;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\NullDatum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BindingRow::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(NullDatum::class)]
#[Small]
final class BindingRowTest extends TestCase
{
    public function testUnitBindsNothingButIsStillOneRow(): void
    {
        self::assertSame([], BindingRow::unit()->names());
    }

    public function testHasReportsANameNothingBoundAsUnbound(): void
    {
        self::assertFalse(BindingRow::unit()->has('p'));
    }

    public function testHasReportsANameBoundToNothingAsBound(): void
    {
        self::assertTrue(BindingRow::unit()->with('p', new NullDatum())->has('p'));
    }

    public function testValueReadsWhatANameWasBoundTo(): void
    {
        self::assertSame('3', BindingRow::unit()->with('n', new IntegerDatum(3))->value('n')->toText());
    }

    public function testValueReadsANameNothingBoundAsAbsent(): void
    {
        self::assertSame(DatumKind::Null, BindingRow::unit()->value('p')->kind());
    }

    public function testWithLeavesTheRowItWasBoundInAlone(): void
    {
        $before = BindingRow::unit();
        $before->with('n', new IntegerDatum(3));

        self::assertFalse($before->has('n'));
    }

    public function testWithAllBindsEverythingAPatternFoundAtOnce(): void
    {
        self::assertSame(['a', 'b'], BindingRow::unit()->withAll(['a' => new NullDatum(), 'b' => new NullDatum()])->names());
    }

    public function testWithAllBindingNothingIsTheRowItWasGiven(): void
    {
        $row = BindingRow::unit();

        self::assertSame($row, $row->withAll([]));
    }

    public function testNamesComeBackInTheOrderTheyWereBound(): void
    {
        self::assertSame(['p', 'e'], BindingRow::unit()->withAll(['p' => new NullDatum(), 'e' => new NullDatum()])->names());
    }

    public function testValuesAreEverythingTheRowBinds(): void
    {
        self::assertSame([], BindingRow::unit()->values());
    }
}
