<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Datum;

use App\Gql\Datum\DateTimeDatum;
use App\Gql\Datum\DatumKind;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DateTimeDatum::class)]
#[UsesClass(DatumKind::class)]
#[Small]
final class DateTimeDatumTest extends TestCase
{
    public function testAMomentCarriesWhatItWasGiven(): void
    {
        $moment = new DateTimeImmutable('2024-01-15T10:30:00+00:00');

        self::assertSame($moment, (new DateTimeDatum($moment))->value);
    }

    public function testKindNamesAMomentWithTheOffsetItIsWrittenIn(): void
    {
        self::assertSame(DatumKind::DateTime, (new DateTimeDatum(new DateTimeImmutable()))->kind());
    }

    public function testToTextWritesTheMomentInTheFormAQueryReadsOneIn(): void
    {
        $moment = new DateTimeImmutable('2024-01-15T10:30:00+00:00');

        self::assertSame('2024-01-15T10:30:00+00:00', (new DateTimeDatum($moment))->toText());
    }

    public function testToTextKeepsTheOffsetRatherThanNormalisingIt(): void
    {
        $moment = new DateTimeImmutable('2024-01-15T10:30:00+09:00');

        self::assertSame('2024-01-15T10:30:00+09:00', (new DateTimeDatum($moment))->toText());
    }
}
