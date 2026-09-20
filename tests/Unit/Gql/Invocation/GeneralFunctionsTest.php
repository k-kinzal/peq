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
use App\Gql\Invocation\GeneralFunctions;
use App\Gql\StatusCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(GeneralFunctions::class)]
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
#[Small]
final class GeneralFunctionsTest extends TestCase
{
    public function testCoalesceAnswersWithTheFirstValueThatIsThere(): void
    {
        self::assertSame('Unknown', GeneralFunctions::coalesce([new NullDatum(), new StringDatum('Unknown')])->toText());
    }

    public function testCoalesceAnswersWithNothingWhenNoneOfThemIsThere(): void
    {
        self::assertSame(DatumKind::Null, GeneralFunctions::coalesce([new NullDatum()])->kind());
    }

    public function testCoalesceAnswersWithNothingWhenThereIsNothingToChooseBetween(): void
    {
        self::assertSame(DatumKind::Null, GeneralFunctions::coalesce([])->kind());
    }

    public function testNullifWithdrawsAValueThatEqualsTheOneWithdrawn(): void
    {
        $withdrawn = GeneralFunctions::nullif(new StringDatum('unknown'), new StringDatum('unknown'));

        self::assertSame(DatumKind::Null, $withdrawn->kind());
    }

    public function testNullifLeavesAnyOtherValueAsItIs(): void
    {
        self::assertSame('public', GeneralFunctions::nullif(new StringDatum('public'), new StringDatum('unknown'))->toText());
    }

    /**
     * @throws GqlException
     */
    public function testZonedDatetimeReadsAMomentWrittenInIsoEightThousandSixHundredAndOne(): void
    {
        $written = [new StringDatum('2024-01-15T10:30:00+00:00')];

        self::assertSame('2024-01-15T10:30:00+00:00', GeneralFunctions::zonedDatetime($written)->toText());
    }

    /**
     * @throws GqlException
     */
    public function testZonedDatetimeAnswersWithNowWhenAskedForNothing(): void
    {
        self::assertSame(DatumKind::DateTime, GeneralFunctions::zonedDatetime([])->kind());
    }

    /**
     * @throws GqlException
     */
    public function testZonedDatetimeReadsNoMomentOffSomethingThatIsNotThere(): void
    {
        self::assertSame(DatumKind::Null, GeneralFunctions::zonedDatetime([new NullDatum()])->kind());
    }

    /**
     * @throws GqlException
     */
    public function testZonedDatetimeReportsSomethingThatIsNotAMoment(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('"yesterday-ish" is not a moment in time');

        GeneralFunctions::zonedDatetime([new StringDatum('yesterday-ish')]);
    }
}
