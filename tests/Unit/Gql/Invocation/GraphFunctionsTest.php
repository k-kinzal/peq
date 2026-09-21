<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Invocation;

use App\Gql\Datum\DatumKind;
use App\Gql\Datum\EdgeDatum;
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
#[UsesClass(DatumKind::class)]
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
final class GraphFunctionsTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testElementsReadsEverythingAPathIsMadeOfInTheOrderItWasWalked(): void
    {
        $path = new PathDatum([new NodeDatum('a'), new EdgeDatum('e', ['calls'], [], 'a', 'b'), new NodeDatum('b')]);

        self::assertEquals(
            new ListDatum([new NodeDatum('a'), new EdgeDatum('e', ['calls'], [], 'a', 'b'), new NodeDatum('b')]),
            GraphFunctions::elements($path),
        );
    }

    /**
     * @throws GqlException
     */
    public function testElementsReadsNothingOffSomethingThatIsNotThere(): void
    {
        self::assertEquals(new NullDatum(), GraphFunctions::elements(new NullDatum()));
    }

    /**
     * @throws GqlException
     */
    public function testElementsReportsSomethingThatIsNotAPath(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G03] error: data exception - invalid value type: a path was expected, and a LIST was given');

        GraphFunctions::elements(new ListDatum([new NodeDatum('a')]));
    }

    /**
     * @throws GqlException
     */
    public function testPathLengthCountsTheRelationsAPathCrosses(): void
    {
        $path = new PathDatum([
            new NodeDatum('a'),
            new EdgeDatum('e', [], [], 'a', 'b'),
            new NodeDatum('b'),
            new EdgeDatum('f', [], [], 'b', 'c'),
            new NodeDatum('c'),
        ]);

        self::assertEquals(new IntegerDatum(2), GraphFunctions::pathLength($path));
    }

    /**
     * @throws GqlException
     */
    public function testPathLengthOfAPathThatCrossesNothingIsNothing(): void
    {
        self::assertEquals(new IntegerDatum(0), GraphFunctions::pathLength(new PathDatum([new NodeDatum('a')])));
    }

    /**
     * @throws GqlException
     */
    public function testPathLengthMeasuresNothingThatIsNotThere(): void
    {
        self::assertEquals(new NullDatum(), GraphFunctions::pathLength(new NullDatum()));
    }

    /**
     * @throws GqlException
     */
    public function testPathReadsAPathAsItself(): void
    {
        $path = new PathDatum([new NodeDatum('a')]);

        self::assertSame($path, GraphFunctions::path($path));
    }

    /**
     * @throws GqlException
     */
    public function testPathReportsAnythingElseUnderTheStatusGqlGivesIt(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G03] error: data exception - invalid value type: a path was expected, and a STRING was given');

        GraphFunctions::path(new StringDatum('a'));
    }
}
