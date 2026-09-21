<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Invocation;

use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DecimalDatum;
use App\Gql\Datum\EdgeDatum;
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
#[UsesClass(DatumKind::class)]
#[UsesClass(DecimalDatum::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(PathDatum::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(StringDatum::class)]
#[Small]
final class ListFunctionsTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testSizeCountsTheValuesAListHolds(): void
    {
        self::assertEquals(new IntegerDatum(2), ListFunctions::size(new ListDatum([new IntegerDatum(1), new NullDatum()])));
    }

    /**
     * @throws GqlException
     */
    public function testSizeCountsTheRelationsAPathCrosses(): void
    {
        $path = new PathDatum([new NodeDatum('a'), new EdgeDatum('e', [], [], 'a', 'b'), new NodeDatum('b')]);

        self::assertEquals(new IntegerDatum(1), ListFunctions::size($path));
    }

    /**
     * @throws GqlException
     */
    public function testSizeFindsNoSizeInSomethingThatIsNotThere(): void
    {
        self::assertEquals(new NullDatum(), ListFunctions::size(new NullDatum()));
    }

    /**
     * @throws GqlException
     */
    public function testTrimToCutsAListDownToTheLengthAskedFor(): void
    {
        $rows = new ListDatum([new IntegerDatum(1), new IntegerDatum(2)]);

        self::assertEquals(new ListDatum([new IntegerDatum(1)]), ListFunctions::trimTo($rows, new IntegerDatum(1)));
    }

    /**
     * @throws GqlException
     */
    public function testTrimToKeepsAListShorterThanTheLengthAskedForWhole(): void
    {
        $rows = new ListDatum([new IntegerDatum(1)]);

        self::assertEquals(new ListDatum([new IntegerDatum(1)]), ListFunctions::trimTo($rows, new IntegerDatum(5)));
    }

    /**
     * @throws GqlException
     */
    public function testTrimToKeepsNothingWhenAskedForFewerThanNone(): void
    {
        self::assertEquals(new ListDatum([]), ListFunctions::trimTo(new ListDatum([new IntegerDatum(1)]), new IntegerDatum(-1)));
    }

    /**
     * @throws GqlException
     */
    public function testTrimToCutsNothingWhenThereIsNoListToCut(): void
    {
        self::assertEquals(new NullDatum(), ListFunctions::trimTo(new NullDatum(), new IntegerDatum(1)));
    }

    /**
     * @throws GqlException
     */
    public function testTrimToCutsNothingWhenThereIsNoLengthToCutTo(): void
    {
        self::assertEquals(new NullDatum(), ListFunctions::trimTo(new ListDatum([new IntegerDatum(1)]), new NullDatum()));
    }

    /**
     * @throws GqlException
     */
    public function testTrimToReportsALengthThatIsNotAWholeNumber(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G03] error: data exception - invalid value type: a whole number was expected, and a STRING was given');

        ListFunctions::trimTo(new ListDatum([]), new StringDatum('1'));
    }

    /**
     * @throws GqlException
     */
    public function testTrimToReportsALengthWrittenWithDigitsAfterItsPoint(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G03] error: data exception - invalid value type: a whole number was expected, and a DECIMAL was given');

        ListFunctions::trimTo(new ListDatum([]), new DecimalDatum(10, 1));
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
        $path = new PathDatum([new NodeDatum('a'), new EdgeDatum('e', [], [], 'a', 'b'), new NodeDatum('b')]);

        self::assertEquals([new EdgeDatum('e', [], [], 'a', 'b')], ListFunctions::items($path));
    }

    /**
     * @throws GqlException
     */
    public function testItemsReportsAnythingElseUnderTheStatusGqlGivesIt(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G03] error: data exception - invalid value type: a list was expected, and a STRING was given');

        ListFunctions::items(new StringDatum('a'));
    }
}
