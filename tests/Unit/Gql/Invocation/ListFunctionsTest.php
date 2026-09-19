<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Invocation;

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
use App\Gql\StatusCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ListFunctions::class)]
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
#[Small]
final class ListFunctionsTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testSizeCountsTheValuesAListHolds(): void
    {
        self::assertSame('2', ListFunctions::size(new ListDatum([new IntegerDatum(1), new IntegerDatum(2)]))->toText());
    }

    /**
     * @throws GqlException
     */
    public function testSizeCountsTheRelationsAPathCrosses(): void
    {
        $path = PathDatum::at(new NodeDatum('a'))
            ->continuedBy(new EdgeDatum('e', [], [], 'a', 'b'), new NodeDatum('b'))
        ;

        self::assertSame('1', ListFunctions::size($path)->toText());
    }

    /**
     * @throws GqlException
     */
    public function testSizeFindsNoSizeInSomethingThatIsNotThere(): void
    {
        self::assertSame(DatumKind::Null, ListFunctions::size(new NullDatum())->kind());
    }

    /**
     * @throws GqlException
     */
    public function testTrimToCutsAListDownToTheLengthAskedFor(): void
    {
        $rows = new ListDatum([new IntegerDatum(1), new IntegerDatum(2)]);

        self::assertSame('[1]', ListFunctions::trimTo($rows, new IntegerDatum(1))->toText());
    }

    /**
     * @throws GqlException
     */
    public function testTrimToKeepsNothingWhenAskedForFewerThanNone(): void
    {
        $rows = new ListDatum([new IntegerDatum(1)]);

        self::assertSame('[]', ListFunctions::trimTo($rows, new IntegerDatum(-1))->toText());
    }

    /**
     * @throws GqlException
     */
    public function testTrimToCutsNothingWhenThereIsNoListToCut(): void
    {
        self::assertSame(DatumKind::Null, ListFunctions::trimTo(new NullDatum(), new IntegerDatum(1))->kind());
    }

    /**
     * @throws GqlException
     */
    public function testTrimToReportsALengthThatIsNotAWholeNumber(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('a whole number was expected, and a STRING was given');

        ListFunctions::trimTo(new ListDatum([]), new StringDatum('1'));
    }

    /**
     * @throws GqlException
     */
    public function testItemsReadsAListAsTheValuesItHolds(): void
    {
        self::assertEquals([new IntegerDatum(1)], ListFunctions::items(new ListDatum([new IntegerDatum(1)])));
    }

    /**
     * @throws GqlException
     */
    public function testItemsReadsAPathAsTheRelationsItCrosses(): void
    {
        $path = PathDatum::at(new NodeDatum('a'))
            ->continuedBy(new EdgeDatum('e', [], [], 'a', 'b'), new NodeDatum('b'))
        ;

        self::assertCount(1, ListFunctions::items($path));
    }

    /**
     * @throws GqlException
     */
    public function testItemsReportsAnythingElseUnderTheStatusGqlGivesIt(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('a list was expected, and a STRING was given');

        ListFunctions::items(new StringDatum('a'));
    }
}
