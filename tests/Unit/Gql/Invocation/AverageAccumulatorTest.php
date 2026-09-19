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
use App\Gql\Invocation\AverageAccumulator;
use App\Gql\StatusCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AverageAccumulator::class)]
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
final class AverageAccumulatorTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testAcceptAveragesWholeNumbersIntoAnApproximateOne(): void
    {
        $average = new AverageAccumulator();
        $average->accept(new IntegerDatum(1));
        $average->accept(new IntegerDatum(2));

        self::assertSame('1.5', $average->result()->toText());
    }

    /**
     * @throws GqlException
     */
    public function testAcceptPassesOverAValueThatIsNotThereWithoutCountingItAgainstTheAverage(): void
    {
        $average = new AverageAccumulator();
        $average->accept(new IntegerDatum(2));
        $average->accept(new NullDatum());

        self::assertSame('2.0', $average->result()->toText());
    }

    /**
     * @throws GqlException
     */
    public function testAcceptReportsAValueThatIsNotANumber(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('a number was expected');

        (new AverageAccumulator())->accept(new StringDatum('a'));
    }

    public function testResultAnswersNothingWhenNothingWasAveraged(): void
    {
        self::assertSame(DatumKind::Null, (new AverageAccumulator())->result()->kind());
    }
}
