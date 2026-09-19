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
    public function testJoinWritesTheValuesOfAListOneAfterAnother(): void
    {
        $names = new ListDatum([new StringDatum('a'), new StringDatum('b')]);

        self::assertSame('a, b', TextFunctions::join($names, new StringDatum(', '))->toText());
    }

    /**
     * @throws GqlException
     */
    public function testJoinLeavesOutAValueThatIsNotThereRatherThanWriteNothing(): void
    {
        $names = new ListDatum([new StringDatum('a'), new NullDatum(), new StringDatum('b')]);

        self::assertSame('a, b', TextFunctions::join($names, new StringDatum(', '))->toText());
    }

    /**
     * @throws GqlException
     */
    public function testJoinJoinsNothingWhenThereIsNoListToJoin(): void
    {
        self::assertSame(DatumKind::Null, TextFunctions::join(new NullDatum(), new StringDatum(', '))->kind());
    }

    /**
     * @throws GqlException
     */
    public function testJoinJoinsNothingWhenThereIsNothingToWriteBetween(): void
    {
        self::assertSame(DatumKind::Null, TextFunctions::join(new ListDatum([]), new NullDatum())->kind());
    }
}
