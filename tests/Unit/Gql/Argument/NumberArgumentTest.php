<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Argument;

use App\Gql\Argument\NumberArgument;
use App\Gql\Datum\DatumKind;
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
        $this->expectExceptionMessage('a number was expected, and a STRING was given');

        NumberArgument::of(new StringDatum('2'));
    }

    /**
     * @throws GqlException
     */
    public function testOfReportsTheAbsenceOfAValueAsNotANumber(): void
    {
        $this->expectException(GqlException::class);

        NumberArgument::of(new NullDatum());
    }

    public function testApproximateReportsANumberThatMakesWhatItTouchesApproximate(): void
    {
        self::assertTrue(NumberArgument::approximate(new FloatDatum(1.5)));
    }

    public function testApproximateDoesNotReportAWholeNumber(): void
    {
        self::assertFalse(NumberArgument::approximate(new IntegerDatum(2)));
    }
}
