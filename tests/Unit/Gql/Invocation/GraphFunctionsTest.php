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
use App\Gql\Invocation\GraphFunctions;
use App\Gql\StatusCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(GraphFunctions::class)]
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
final class GraphFunctionsTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testElementsReadsEverythingAPathIsMadeOf(): void
    {
        $path = PathDatum::at(new NodeDatum('a'))
            ->continuedBy(new EdgeDatum('e', [], [], 'a', 'b'), new NodeDatum('b'))
        ;

        self::assertSame('[a, a -[]-> b, b]', GraphFunctions::elements($path)->toText());
    }

    /**
     * @throws GqlException
     */
    public function testElementsReadsNothingOffSomethingThatIsNotThere(): void
    {
        self::assertSame(DatumKind::Null, GraphFunctions::elements(new NullDatum())->kind());
    }

    /**
     * @throws GqlException
     */
    public function testPathLengthCountsTheRelationsAPathCrosses(): void
    {
        self::assertSame('0', GraphFunctions::pathLength(PathDatum::at(new NodeDatum('a')))->toText());
    }

    /**
     * @throws GqlException
     */
    public function testPathLengthMeasuresNothingThatIsNotThere(): void
    {
        self::assertSame(DatumKind::Null, GraphFunctions::pathLength(new NullDatum())->kind());
    }

    /**
     * @throws GqlException
     */
    public function testPathReadsAPathAsItself(): void
    {
        self::assertSame(0, GraphFunctions::path(PathDatum::at(new NodeDatum('a')))->length());
    }

    /**
     * @throws GqlException
     */
    public function testPathReportsAnythingElseUnderTheStatusGqlGivesIt(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('a path was expected, and a STRING was given');

        GraphFunctions::path(new StringDatum('a'));
    }
}
