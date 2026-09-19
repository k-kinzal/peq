<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Result;

use App\Gql\Datum\DatumKind;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Result\ResultRow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ResultRow::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(NullDatum::class)]
#[Small]
final class ResultRowTest extends TestCase
{
    public function testARowCarriesItsValuesInTheOrderItsColumnsAreShown(): void
    {
        $value = new IntegerDatum(1);

        self::assertSame([$value], (new ResultRow([$value]))->values);
    }

    public function testValueReadsWhatTheRowHoldsInOneColumn(): void
    {
        self::assertSame('1', (new ResultRow([new IntegerDatum(1)]))->value(0)->toText());
    }

    public function testValueReadsAColumnPastTheEndOfTheRowAsAbsent(): void
    {
        self::assertSame(DatumKind::Null, (new ResultRow([]))->value(3)->kind());
    }
}
