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
use App\Gql\Invocation\ExtremeAccumulator;
use App\Gql\StatusCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ExtremeAccumulator::class)]
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
final class ExtremeAccumulatorTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testAcceptKeepsTheSmallestOfWhatItWasOffered(): void
    {
        $least = new ExtremeAccumulator();
        $least->accept(new IntegerDatum(3));
        $least->accept(new IntegerDatum(1));

        self::assertSame('1', $least->result()->toText());
    }

    /**
     * @throws GqlException
     */
    public function testAcceptKeepsTheLargestWhenThatIsWhatWasAskedFor(): void
    {
        $most = new ExtremeAccumulator(true);
        $most->accept(new IntegerDatum(3));
        $most->accept(new IntegerDatum(1));

        self::assertSame('3', $most->result()->toText());
    }

    /**
     * @throws GqlException
     */
    public function testAcceptPassesOverAValueThatIsNotThere(): void
    {
        $least = new ExtremeAccumulator();
        $least->accept(new NullDatum());
        $least->accept(new IntegerDatum(3));

        self::assertSame('3', $least->result()->toText());
    }

    /**
     * @throws GqlException
     */
    public function testAcceptOrdersValuesOfMoreThanOneKindRatherThanRefuseThem(): void
    {
        $least = new ExtremeAccumulator();
        $least->accept(new StringDatum('a'));
        $least->accept(new IntegerDatum(3));

        self::assertSame('3', $least->result()->toText());
    }

    public function testResultAnswersNothingWhenNothingWasOffered(): void
    {
        self::assertSame(DatumKind::Null, (new ExtremeAccumulator())->result()->kind());
    }
}
