<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Datum;

use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DecimalDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\GqlException;
use App\Gql\StatusCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DecimalDatum::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(StatusCode::class)]
#[Small]
final class DecimalDatumTest extends TestCase
{
    public function testADecimalCarriesItsDigitsAndHowManyOfThemComeAfterThePoint(): void
    {
        $decimal = new DecimalDatum(15, 1);

        self::assertSame(15, $decimal->unscaled);
        self::assertSame(1, $decimal->scale);
    }

    /**
     * @throws GqlException
     */
    public function testWrittenReadsANumberWithAPointAsAnExactOne(): void
    {
        self::assertEquals(new DecimalDatum(15, 1), DecimalDatum::written('1.5'));
    }

    /**
     * @throws GqlException
     */
    public function testWrittenKeepsTheZerosWrittenAfterThePoint(): void
    {
        self::assertEquals(new DecimalDatum(150, 2), DecimalDatum::written('1.50'));
    }

    /**
     * @throws GqlException
     */
    public function testWrittenReadsAPointWithNothingBeforeIt(): void
    {
        self::assertEquals(new DecimalDatum(5, 1), DecimalDatum::written('.5'));
    }

    /**
     * @throws GqlException
     */
    public function testWrittenMovesThePointLeftForANegativeExponent(): void
    {
        self::assertEquals(new DecimalDatum(15, 2), DecimalDatum::written('1.5e-1M'));
    }

    /**
     * @throws GqlException
     */
    public function testWrittenReadsANumberWithNothingLeftAfterItsPointAsAWholeNumber(): void
    {
        self::assertEquals(new IntegerDatum(15), DecimalDatum::written('1.5e1M'));
    }

    /**
     * @throws GqlException
     */
    public function testWrittenFillsInTheZerosAPositiveExponentAsksFor(): void
    {
        self::assertEquals(new IntegerDatum(100), DecimalDatum::written('1e2'));
    }

    /**
     * @throws GqlException
     */
    public function testWrittenReadsDigitsMarkedExactAsAWholeNumber(): void
    {
        self::assertEquals(new IntegerDatum(15), DecimalDatum::written('15M'));
    }

    /**
     * @throws GqlException
     */
    public function testWrittenReportsANumberWithMoreDigitsThanAnExactNumberCanHold(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22003] error: data exception - numeric value out of range');

        DecimalDatum::written('99999999999999999999.5');
    }

    public function testOfReturnsAWholeNumberForAScaleOfNothing(): void
    {
        self::assertEquals(new IntegerDatum(15), DecimalDatum::of(15, 0));
    }

    public function testOfReturnsADecimalForAnyOtherScale(): void
    {
        self::assertEquals(new DecimalDatum(15, 1), DecimalDatum::of(15, 1));
    }

    /**
     * @throws GqlException
     */
    public function testWholeReadsDigitsAsTheNumberTheySpell(): void
    {
        self::assertSame(42, DecimalDatum::whole('0042'));
    }

    /**
     * @throws GqlException
     */
    public function testWholeReadsTheLargestNumberAnExactNumberCanHold(): void
    {
        self::assertSame(PHP_INT_MAX, DecimalDatum::whole('9223372036854775807'));
    }

    /**
     * @throws GqlException
     */
    public function testWholeReportsTheFirstNumberTooLargeToHold(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22003] error: data exception - numeric value out of range: 9223372036854775808 has more digits than an exact number can hold');

        DecimalDatum::whole('9223372036854775808');
    }

    /**
     * @throws GqlException
     */
    public function testWholeReportsANumberWithMoreDigitsThanItCanHold(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22003] error: data exception - numeric value out of range');

        DecimalDatum::whole('99999999999999999999');
    }

    public function testKindNamesAnExactNumberWithDigitsAfterThePoint(): void
    {
        self::assertSame(DatumKind::Decimal, (new DecimalDatum(15, 1))->kind());
    }

    public function testToTextWritesExactlyAsManyDigitsAfterThePointAsItsScale(): void
    {
        self::assertSame('1.50', (new DecimalDatum(150, 2))->toText());
    }

    public function testToTextWritesAZeroBeforeThePointOfANumberSmallerThanOne(): void
    {
        self::assertSame('-0.05', (new DecimalDatum(-5, 2))->toText());
    }

    public function testToTextWritesZeroWithTheDigitsItsScaleGivesIt(): void
    {
        self::assertSame('0.00', (new DecimalDatum(0, 2))->toText());
    }

    public function testToFloatReturnsTheNearestApproximateNumber(): void
    {
        self::assertSame(1.5, (new DecimalDatum(15, 1))->toFloat());
    }
}
