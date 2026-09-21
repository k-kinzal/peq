<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Datum;

use App\Gql\Datum\DatumKind;
use App\Gql\Datum\NullDatum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(NullDatum::class)]
#[UsesClass(DatumKind::class)]
#[Small]
final class NullDatumTest extends TestCase
{
    public function testUnknownIsTheAbsenceOfAValue(): void
    {
        self::assertSame(DatumKind::Null, NullDatum::unknown()->kind());
    }

    public function testKindNamesTheAbsenceOfAValue(): void
    {
        self::assertSame(DatumKind::Null, (new NullDatum())->kind());
    }

    public function testToTextWritesTheWordGqlWritesForIt(): void
    {
        self::assertSame('NULL', (new NullDatum())->toText());
    }
}
