<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Datum;

use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\DatumKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BooleanDatum::class)]
#[UsesClass(DatumKind::class)]
#[Small]
final class BooleanDatumTest extends TestCase
{
    public function testOfCarriesTheTruthValueItWasGiven(): void
    {
        self::assertTrue(BooleanDatum::of(true)->value);
    }

    public function testKindNamesATruthValue(): void
    {
        self::assertSame(DatumKind::Boolean, (new BooleanDatum(false))->kind());
    }

    public function testToTextWritesTruthTheWayAQueryWritesIt(): void
    {
        self::assertSame('TRUE', (new BooleanDatum(true))->toText());
    }

    public function testToTextWritesItsOppositeTheSameWay(): void
    {
        self::assertSame('FALSE', (new BooleanDatum(false))->toText());
    }
}
