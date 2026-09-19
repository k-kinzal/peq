<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Datum;

use App\Gql\Datum\DatumKind;
use App\Gql\Datum\StringDatum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(StringDatum::class)]
#[UsesClass(DatumKind::class)]
#[Small]
final class StringDatumTest extends TestCase
{
    public function testOfCarriesTheCharactersItWasGiven(): void
    {
        self::assertSame('App\Domain\Invoice', StringDatum::of('App\Domain\Invoice')->value);
    }

    public function testKindNamesACharacterString(): void
    {
        self::assertSame(DatumKind::Text, (new StringDatum('a'))->kind());
    }

    public function testToTextShowsTheCharactersWithNoQuotingOfItsOwn(): void
    {
        self::assertSame('App\Domain\Invoice', (new StringDatum('App\Domain\Invoice'))->toText());
    }

    public function testAStringCanHoldNothingAtAll(): void
    {
        self::assertSame('', (new StringDatum(''))->toText());
    }
}
