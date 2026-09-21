<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Invocation;

use App\Gql\Argument\NumberArgument;
use App\Gql\Argument\TextArgument;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DecimalDatum;
use App\Gql\Datum\FloatDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\GqlException;
use App\Gql\Invocation\TextFunctions;
use App\Gql\StatusCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TextFunctions::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DecimalDatum::class)]
#[UsesClass(FloatDatum::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(NumberArgument::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(TextArgument::class)]
#[Small]
final class TextFunctionsTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testCharLengthCountsTheCharactersAStringHolds(): void
    {
        self::assertEquals(new IntegerDatum(7), TextFunctions::charLength(new StringDatum('Invoice')));
    }

    /**
     * @throws GqlException
     */
    public function testCharLengthCountsCharactersRatherThanBytes(): void
    {
        self::assertEquals(new IntegerDatum(2), TextFunctions::charLength(new StringDatum('é!')));
    }

    /**
     * @throws GqlException
     */
    public function testCharLengthFindsNoLengthInSomethingThatIsNotThere(): void
    {
        self::assertEquals(new NullDatum(), TextFunctions::charLength(new NullDatum()));
    }

    /**
     * @throws GqlException
     */
    public function testCharLengthReportsAValueThatIsNotAString(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G03] error: data exception - invalid value type: a string was expected, and a LIST was given');

        TextFunctions::charLength(new ListDatum([]));
    }

    /**
     * @throws GqlException
     */
    public function testUpperMapsAStringToUpperCase(): void
    {
        self::assertEquals(new StringDatum('INVOICE'), TextFunctions::upper(new StringDatum('Invoice')));
    }

    /**
     * @throws GqlException
     */
    public function testUpperMapsNothingWhenThereIsNothingToMap(): void
    {
        self::assertEquals(new NullDatum(), TextFunctions::upper(new NullDatum()));
    }

    /**
     * @throws GqlException
     */
    public function testLowerMapsAStringToLowerCase(): void
    {
        self::assertEquals(new StringDatum('invoice'), TextFunctions::lower(new StringDatum('Invoice')));
    }

    /**
     * @throws GqlException
     */
    public function testLowerMapsNothingWhenThereIsNothingToMap(): void
    {
        self::assertEquals(new NullDatum(), TextFunctions::lower(new NullDatum()));
    }

    /**
     * @throws GqlException
     */
    public function testTrimTakesTheWhitespaceOffBothEnds(): void
    {
        self::assertEquals(new StringDatum('Invoice'), TextFunctions::trim(new StringDatum('  Invoice ')));
    }

    /**
     * @throws GqlException
     */
    public function testTrimTrimsNothingWhenThereIsNothingToTrim(): void
    {
        self::assertEquals(new NullDatum(), TextFunctions::trim(new NullDatum()));
    }

    /**
     * @throws GqlException
     */
    public function testLeftTakesTheFirstCharactersOfAString(): void
    {
        self::assertEquals(new StringDatum('App\Domain'), TextFunctions::left(new StringDatum('App\Domain\Invoice'), new IntegerDatum(10)));
    }

    /**
     * @throws GqlException
     */
    public function testLeftTakesTheWholeStringWhenMoreIsAskedForThanThereIs(): void
    {
        self::assertEquals(new StringDatum('ab'), TextFunctions::left(new StringDatum('ab'), new IntegerDatum(9)));
    }

    /**
     * @throws GqlException
     */
    public function testLeftTakesNothingFromSomethingThatIsNotThere(): void
    {
        self::assertEquals(new NullDatum(), TextFunctions::left(new NullDatum(), new IntegerDatum(1)));
    }

    /**
     * @throws GqlException
     */
    public function testLeftTakesNothingWhenThereIsNoLengthToTake(): void
    {
        self::assertEquals(new NullDatum(), TextFunctions::left(new StringDatum('ab'), new NullDatum()));
    }

    /**
     * @throws GqlException
     */
    public function testRightTakesTheLastCharactersOfAString(): void
    {
        self::assertEquals(new StringDatum('Controller'), TextFunctions::right(new StringDatum('UserController'), new IntegerDatum(10)));
    }

    /**
     * @throws GqlException
     */
    public function testRightTakesNoneOfAStringWhenNoneIsAskedFor(): void
    {
        self::assertEquals(new StringDatum(''), TextFunctions::right(new StringDatum('ab'), new IntegerDatum(0)));
    }

    /**
     * @throws GqlException
     */
    public function testRightTakesTheWholeStringWhenMoreIsAskedForThanThereIs(): void
    {
        self::assertEquals(new StringDatum('ab'), TextFunctions::right(new StringDatum('ab'), new IntegerDatum(9)));
    }

    /**
     * @throws GqlException
     */
    public function testRightTakesNothingFromSomethingThatIsNotThere(): void
    {
        self::assertEquals(new NullDatum(), TextFunctions::right(new NullDatum(), new IntegerDatum(1)));
    }

    /**
     * @throws GqlException
     */
    public function testLengthReadsAWholeNumberAsALength(): void
    {
        self::assertSame(3, TextFunctions::length(new IntegerDatum(3)));
    }

    /**
     * @throws GqlException
     */
    public function testLengthReportsALengthThatIsNegative(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22011] error: data exception - substring error: a substring is as many characters long as a whole number that is not negative, not -1');

        TextFunctions::length(new IntegerDatum(-1));
    }

    /**
     * @throws GqlException
     */
    public function testLengthReportsAnApproximateLength(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22011] error: data exception - substring error: a substring is as many characters long as a whole number that is not negative, not 1.5');

        TextFunctions::length(new FloatDatum(1.5));
    }

    /**
     * @throws GqlException
     */
    public function testLengthReportsALengthWrittenWithDigitsAfterItsPoint(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22011] error: data exception - substring error: a substring is as many characters long as a whole number that is not negative, not 2.0');

        TextFunctions::length(new DecimalDatum(20, 1));
    }

    /**
     * @throws GqlException
     */
    public function testLengthReportsALengthThatIsNotANumber(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G03] error: data exception - invalid value type: a number was expected, and a STRING was given');

        TextFunctions::length(new StringDatum('3'));
    }
}
