<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Datum;

use App\Gql\Datum\DatumKind;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NullDatum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ListDatum::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(NullDatum::class)]
#[Small]
final class ListDatumTest extends TestCase
{
    public function testAListCarriesItsValuesInTheOrderItWasGivenThem(): void
    {
        $first = new IntegerDatum(1);
        $second = new IntegerDatum(2);

        self::assertSame([$first, $second], (new ListDatum([$first, $second]))->items);
    }

    public function testEmptyIsAListThatHoldsNothing(): void
    {
        self::assertSame([], ListDatum::empty()->items);
    }

    public function testKindNamesAListWhateverItHolds(): void
    {
        self::assertSame(DatumKind::ListOf, ListDatum::empty()->kind());
    }

    public function testToTextWritesTheValuesInTheOrderTheListHoldsThem(): void
    {
        self::assertSame('[1, 2]', (new ListDatum([new IntegerDatum(1), new IntegerDatum(2)]))->toText());
    }

    public function testToTextWritesAListOfNothingAsAnEmptyOne(): void
    {
        self::assertSame('[]', ListDatum::empty()->toText());
    }

    public function testToTextWritesAValueThatIsNotThereAsTheAbsenceItIs(): void
    {
        self::assertSame('[NULL]', (new ListDatum([new NullDatum()]))->toText());
    }
}
