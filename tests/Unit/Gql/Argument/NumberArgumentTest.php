<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Argument;

use App\Gql\Argument\NumberArgument;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DecimalDatum;
use App\Gql\Datum\FloatDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\GqlException;
use App\Gql\StatusCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(NumberArgument::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DecimalDatum::class)]
#[UsesClass(FloatDatum::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(StringDatum::class)]
#[Small]
final class NumberArgumentTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testOfReadsAWholeNumberAsAnIntegerAndKeepsItOne(): void
    {
        self::assertSame(2, NumberArgument::of(new IntegerDatum(2)));
    }

    /**
     * @throws GqlException
     */
    public function testOfReadsADecimalAsTheFloatNearestToIt(): void
    {
        self::assertSame(1.5, NumberArgument::of(new DecimalDatum(15, 1)));
    }

    /**
     * @throws GqlException
     */
    public function testOfReadsAnApproximateNumberAsAFloat(): void
    {
        self::assertSame(1.5, NumberArgument::of(new FloatDatum(1.5)));
    }

    /**
     * @throws GqlException
     */
    public function testOfReportsAValueThatIsNotANumber(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G03] error: data exception - invalid value type: a number was expected, and a STRING was given');

        NumberArgument::of(new StringDatum('2'));
    }

    /**
     * @throws GqlException
     */
    public function testOfReportsTheAbsenceOfAValueAsNotANumber(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('a number was expected, and a NULL was given');

        NumberArgument::of(new NullDatum());
    }

    /**
     * @throws GqlException
     */
    public function testExactReturnsAWholeNumberAsItIs(): void
    {
        $whole = new IntegerDatum(2);

        self::assertSame($whole, NumberArgument::exact($whole));
    }

    /**
     * @throws GqlException
     */
    public function testExactReturnsADecimalAsItIs(): void
    {
        $decimal = new DecimalDatum(15, 1);

        self::assertSame($decimal, NumberArgument::exact($decimal));
    }

    /**
     * @throws GqlException
     */
    public function testExactFindsNoExactNumberInAnApproximateOne(): void
    {
        self::assertNull(NumberArgument::exact(new FloatDatum(1.5)));
    }

    /**
     * @throws GqlException
     */
    public function testExactReportsAValueThatIsNotANumberAtAll(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G03] error: data exception - invalid value type: a number was expected, and a STRING was given');

        NumberArgument::exact(new StringDatum('2'));
    }

    public function testApproximateReportsANumberThatMakesWhatItTouchesApproximate(): void
    {
        self::assertTrue(NumberArgument::approximate(new FloatDatum(1.5)));
    }

    public function testApproximateDoesNotReportAWholeNumber(): void
    {
        self::assertFalse(NumberArgument::approximate(new IntegerDatum(2)));
    }

    public function testApproximateDoesNotReportADecimal(): void
    {
        self::assertFalse(NumberArgument::approximate(new DecimalDatum(15, 1)));
    }
}
