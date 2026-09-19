<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Datum;

use App\Gql\Datum\DatumKind;
use App\Gql\Datum\IntegerDatum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(IntegerDatum::class)]
#[UsesClass(DatumKind::class)]
#[Small]
final class IntegerDatumTest extends TestCase
{
    public function testOfCarriesTheNumberItWasGiven(): void
    {
        self::assertSame(42, IntegerDatum::of(42)->value);
    }

    public function testKindNamesAWholeNumber(): void
    {
        self::assertSame(DatumKind::Integer, (new IntegerDatum(42))->kind());
    }

    public function testToTextWritesTheNumberWithoutAFractionalPart(): void
    {
        self::assertSame('42', (new IntegerDatum(42))->toText());
    }

    public function testToTextKeepsTheSignOfANegativeNumber(): void
    {
        self::assertSame('-42', (new IntegerDatum(-42))->toText());
    }
}
