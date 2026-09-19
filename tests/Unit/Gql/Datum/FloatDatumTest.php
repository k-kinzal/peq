<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Datum;

use App\Gql\Datum\DatumKind;
use App\Gql\Datum\FloatDatum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(FloatDatum::class)]
#[UsesClass(DatumKind::class)]
#[Small]
final class FloatDatumTest extends TestCase
{
    public function testOfCarriesTheNumberItWasGiven(): void
    {
        self::assertSame(1.5, FloatDatum::of(1.5)->value);
    }

    public function testKindNamesAnApproximateNumber(): void
    {
        self::assertSame(DatumKind::Float, (new FloatDatum(1.5))->kind());
    }

    #[DataProvider('providerNumbersAndHowTheyAreWritten')]
    public function testToTextSaysThatTheNumberIsApproximate(float $value, string $written): void
    {
        self::assertSame($written, (new FloatDatum($value))->toText());
    }

    /**
     * @return iterable<string, array{float, string}>
     */
    public static function providerNumbersAndHowTheyAreWritten(): iterable
    {
        yield 'with a fractional part' => [1.5, '1.5'];

        yield 'that came out whole' => [2.0, '2.0'];

        yield 'that is negative' => [-1.5, '-1.5'];

        yield 'that is very large' => [1.0E+25, '1.0E+25'];

        yield 'that is not a number at all' => [NAN, 'NAN'];

        yield 'that is beyond every number' => [INF, 'INF'];
    }
}
