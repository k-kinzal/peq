<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Invocation;

use App\Gql\Datum\DatumKind;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\GqlException;
use App\Gql\Invocation\CollectAccumulator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CollectAccumulator::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(StringDatum::class)]
#[Small]
final class CollectAccumulatorTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testAcceptKeepsEveryValueOfferedInTheOrderItWasOffered(): void
    {
        $collected = new CollectAccumulator();
        $collected->accept(new IntegerDatum(2));
        $collected->accept(new IntegerDatum(1));
        $collected->accept(new IntegerDatum(2));

        self::assertEquals(
            new ListDatum([new IntegerDatum(2), new IntegerDatum(1), new IntegerDatum(2)]),
            $collected->result(),
        );
    }

    /**
     * @throws GqlException
     */
    public function testAcceptKeepsValuesOfMoreThanOneKind(): void
    {
        $collected = new CollectAccumulator();
        $collected->accept(new StringDatum('a'));
        $collected->accept(new IntegerDatum(1));

        self::assertEquals(new ListDatum([new StringDatum('a'), new IntegerDatum(1)]), $collected->result());
    }

    /**
     * @throws GqlException
     */
    public function testAcceptPassesOverAValueThatIsNotThere(): void
    {
        $collected = new CollectAccumulator();
        $collected->accept(new NullDatum());
        $collected->accept(new IntegerDatum(1));

        self::assertEquals(new ListDatum([new IntegerDatum(1)]), $collected->result());
    }

    public function testResultAnswersNothingRatherThanAnEmptyListWhenNothingWasCollected(): void
    {
        self::assertEquals(new NullDatum(), (new CollectAccumulator())->result());
    }
}
