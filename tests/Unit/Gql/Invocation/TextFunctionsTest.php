<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Invocation;

use App\Gql\Argument\NumberArgument;
use App\Gql\Argument\TextArgument;
use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\DateTimeDatum;
use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumIdentity;
use App\Gql\Datum\DatumJson;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DatumOrder;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\FloatDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\PathDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\GqlException;
use App\Gql\Invocation\ListFunctions;
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
#[UsesClass(BooleanDatum::class)]
#[UsesClass(DateTimeDatum::class)]
#[UsesClass(DatumIdentity::class)]
#[UsesClass(DatumJson::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DatumOrder::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(FloatDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(PathDatum::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(Datum::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(NumberArgument::class)]
#[UsesClass(TextArgument::class)]
#[UsesClass(ListFunctions::class)]
#[Small]
final class TextFunctionsTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testCharLengthCountsTheCharactersAStringHolds(): void
    {
        self::assertSame('7', TextFunctions::charLength(new StringDatum('Invoice'))->toText());
    }

    /**
     * @throws GqlException
     */
    public function testCharLengthFindsNoLengthInSomethingThatIsNotThere(): void
    {
        self::assertSame(DatumKind::Null, TextFunctions::charLength(new NullDatum())->kind());
    }

    /**
     * @throws GqlException
     */
    public function testCharLengthReportsAValueThatIsNotAString(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('a string was expected');

        TextFunctions::charLength(new ListDatum([]));
    }

    /**
     * @throws GqlException
     */
    public function testUpperMapsAStringToUpperCase(): void
    {
        self::assertSame('INVOICE', TextFunctions::upper(new StringDatum('Invoice'))->toText());
    }

    /**
     * @throws GqlException
     */
    public function testUpperMapsNothingWhenThereIsNothingToMap(): void
    {
        self::assertSame(DatumKind::Null, TextFunctions::upper(new NullDatum())->kind());
    }

    /**
     * @throws GqlException
     */
    public function testLowerMapsAStringToLowerCase(): void
    {
        self::assertSame('invoice', TextFunctions::lower(new StringDatum('Invoice'))->toText());
    }

    /**
     * @throws GqlException
     */
    public function testLowerMapsNothingWhenThereIsNothingToMap(): void
    {
        self::assertSame(DatumKind::Null, TextFunctions::lower(new NullDatum())->kind());
    }

    /**
     * @throws GqlException
     */
    public function testTrimTakesTheWhitespaceOffBothEnds(): void
    {
        self::assertSame('Invoice', TextFunctions::trim(new StringDatum('  Invoice '))->toText());
    }

    /**
     * @throws GqlException
     */
    public function testTrimTrimsNothingWhenThereIsNothingToTrim(): void
    {
        self::assertSame(DatumKind::Null, TextFunctions::trim(new NullDatum())->kind());
    }

    /**
     * @throws GqlException
     */
    public function testLeftTakesTheFirstCharactersOfAString(): void
    {
        self::assertSame('App\Domain', TextFunctions::left(new StringDatum('App\Domain\Invoice'), new IntegerDatum(10))->toText());
    }

    /**
     * @throws GqlException
     */
    public function testLeftTakesTheWholeStringWhenMoreIsAskedForThanThereIs(): void
    {
        self::assertSame('ab', TextFunctions::left(new StringDatum('ab'), new IntegerDatum(9))->toText());
    }

    /**
     * @throws GqlException
     */
    public function testLeftTakesNothingFromSomethingThatIsNotThere(): void
    {
        self::assertSame(DatumKind::Null, TextFunctions::left(new NullDatum(), new IntegerDatum(1))->kind());
    }

    /**
     * @throws GqlException
     */
    public function testLeftTakesNothingWhenThereIsNoLengthToTake(): void
    {
        self::assertSame(DatumKind::Null, TextFunctions::left(new StringDatum('ab'), new NullDatum())->kind());
    }

    /**
     * @throws GqlException
     */
    public function testRightTakesTheLastCharactersOfAString(): void
    {
        self::assertSame('Controller', TextFunctions::right(new StringDatum('UserController'), new IntegerDatum(10))->toText());
    }

    /**
     * @throws GqlException
     */
    public function testRightTakesNoneOfAStringWhenNoneIsAskedFor(): void
    {
        self::assertSame('', TextFunctions::right(new StringDatum('ab'), new IntegerDatum(0))->toText());
    }

    /**
     * @throws GqlException
     */
    public function testRightTakesTheWholeStringWhenMoreIsAskedForThanThereIs(): void
    {
        self::assertSame('ab', TextFunctions::right(new StringDatum('ab'), new IntegerDatum(9))->toText());
    }

    /**
     * @throws GqlException
     */
    public function testRightTakesNothingFromSomethingThatIsNotThere(): void
    {
        self::assertSame(DatumKind::Null, TextFunctions::right(new NullDatum(), new IntegerDatum(1))->kind());
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
        $this->expectExceptionMessage('substring error');

        TextFunctions::length(new IntegerDatum(-1));
    }

    /**
     * @throws GqlException
     */
    public function testLengthReportsALengthThatIsNotAWholeNumber(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('substring error');

        TextFunctions::length(new FloatDatum(1.5));
    }
}
