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
use App\Gql\Invocation\CountAccumulator;
use App\Gql\StatusCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CountAccumulator::class)]
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
final class CountAccumulatorTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testAcceptPassesOverAValueThatIsNotThere(): void
    {
        $counter = new CountAccumulator();
        $counter->accept(new NullDatum());

        self::assertSame('0', $counter->result()->toText());
    }

    /**
     * @throws GqlException
     */
    public function testAcceptCountsARowWhateverItCarries(): void
    {
        $counter = new CountAccumulator(true);
        $counter->accept(new NullDatum());

        self::assertSame('1', $counter->result()->toText());
    }

    /**
     * @throws GqlException
     */
    public function testAcceptCountsEveryValueThatIsThere(): void
    {
        $counter = new CountAccumulator();
        $counter->accept(new IntegerDatum(1));
        $counter->accept(new IntegerDatum(1));

        self::assertSame('2', $counter->result()->toText());
    }

    public function testResultAnswersZeroRatherThanNothingWhenNothingWasCounted(): void
    {
        self::assertSame('0', (new CountAccumulator())->result()->toText());
    }
}
