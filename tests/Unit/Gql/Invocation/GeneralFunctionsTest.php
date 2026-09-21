<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Invocation;

use App\Gql\Argument\TextArgument;
use App\Gql\Datum\DateTimeDatum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DatumOrder;
use App\Gql\Datum\DecimalDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\GqlException;
use App\Gql\Invocation\GeneralFunctions;
use App\Gql\StatusCode;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(GeneralFunctions::class)]
#[UsesClass(DateTimeDatum::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DatumOrder::class)]
#[UsesClass(DecimalDatum::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(TextArgument::class)]
#[Small]
final class GeneralFunctionsTest extends TestCase
{
    public function testCoalesceAnswersWithTheFirstValueThatIsThere(): void
    {
        self::assertEquals(
            new StringDatum('Unknown'),
            GeneralFunctions::coalesce([new NullDatum(), new StringDatum('Unknown'), new StringDatum('Other')]),
        );
    }

    public function testCoalesceAnswersWithNothingWhenNoneOfThemIsThere(): void
    {
        self::assertEquals(new NullDatum(), GeneralFunctions::coalesce([new NullDatum(), new NullDatum()]));
    }

    public function testCoalesceAnswersWithNothingWhenThereIsNothingToChooseBetween(): void
    {
        self::assertEquals(new NullDatum(), GeneralFunctions::coalesce([]));
    }

    /**
     * @throws GqlException
     */
    public function testNullifWithdrawsAValueThatEqualsTheOneWithdrawn(): void
    {
        self::assertEquals(new NullDatum(), GeneralFunctions::nullif(new StringDatum('unknown'), new StringDatum('unknown')));
    }

    /**
     * @throws GqlException
     */
    public function testNullifWithdrawsANumberEqualToTheOneWithdrawnWhateverKindOfNumberItIs(): void
    {
        self::assertEquals(new NullDatum(), GeneralFunctions::nullif(new DecimalDatum(10, 1), new IntegerDatum(1)));
    }

    /**
     * @throws GqlException
     */
    public function testNullifLeavesAnyOtherValueAsItIs(): void
    {
        self::assertEquals(new StringDatum('public'), GeneralFunctions::nullif(new StringDatum('public'), new StringDatum('unknown')));
    }

    /**
     * @throws GqlException
     */
    public function testNullifLeavesAValueAsItIsWhenWhatIsWithdrawnIsAbsent(): void
    {
        self::assertEquals(new StringDatum('public'), GeneralFunctions::nullif(new StringDatum('public'), new NullDatum()));
    }

    /**
     * @throws GqlException
     */
    public function testNullifReportsTwoValuesOfKindsWithNoComparisonBetweenThem(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G04] error: data exception - values not comparable: INT64 and STRING cannot be compared');

        GeneralFunctions::nullif(new IntegerDatum(1), new StringDatum('1'));
    }

    /**
     * @throws GqlException
     */
    public function testZonedDatetimeReadsAMomentWrittenInIsoEightThousandSixHundredAndOne(): void
    {
        self::assertEquals(
            new DateTimeDatum(new DateTimeImmutable('2024-01-15T10:30:00+00:00')),
            GeneralFunctions::zonedDatetime([new StringDatum('2024-01-15T10:30:00+00:00')]),
        );
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
        self::assertEquals(new NullDatum(), GeneralFunctions::zonedDatetime([new NullDatum()]));
    }

    /**
     * @throws GqlException
     */
    public function testZonedDatetimeReportsSomethingThatIsNotAMoment(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G03] error: data exception - invalid value type: "yesterday-ish" is not a moment in time');

        GeneralFunctions::zonedDatetime([new StringDatum('yesterday-ish')]);
    }

    /**
     * @throws GqlException
     */
    public function testZonedDatetimeReportsAValueThatIsNotAString(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G03] error: data exception - invalid value type: a string was expected, and a INT64 was given');

        GeneralFunctions::zonedDatetime([new IntegerDatum(1705314600)]);
    }
}
