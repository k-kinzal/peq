<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Evaluation;

use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\DatumIdentity;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DatumOrder;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\FloatDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\Evaluation\Logic;
use App\Gql\GqlException;
use App\Gql\StatusCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Logic::class)]
#[UsesClass(BooleanDatum::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DatumIdentity::class)]
#[UsesClass(DatumOrder::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(FloatDatum::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(StatusCode::class)]
#[Small]
final class LogicTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testTruthReadsATruthValueAsItself(): void
    {
        self::assertTrue(Logic::truth(new BooleanDatum(true)));
    }

    /**
     * @throws GqlException
     */
    public function testTruthReadsTheAbsenceOfAValueAsUndecided(): void
    {
        self::assertNull(Logic::truth(new NullDatum()));
    }

    /**
     * @throws GqlException
     */
    public function testTruthReportsSomethingThatIsNotATruthValueAtAll(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('a truth value was expected, and a INT64 was given');

        Logic::truth(new IntegerDatum(1));
    }

    public function testDatumCarriesUndecidedAsTheAbsenceOfAValue(): void
    {
        self::assertSame(DatumKind::Null, Logic::datum(null)->kind());
    }

    public function testDatumCarriesADecidedTruthValueAsItself(): void
    {
        self::assertSame('TRUE', Logic::datum(true)->toText());
    }

    #[DataProvider('providerConjunctions')]
    public function testBothFollowsThreeValuedLogic(?bool $left, ?bool $right, ?bool $expected): void
    {
        self::assertSame($expected, Logic::both($left, $right));
    }

    /**
     * @return iterable<string, array{null|bool, null|bool, null|bool}>
     */
    public static function providerConjunctions(): iterable
    {
        yield 'both true' => [true, true, true];

        yield 'one false' => [true, false, false];

        yield 'false settles it whatever the other side is' => [false, null, false];

        yield 'undecided spreads through a true side' => [true, null, null];

        yield 'undecided on both sides' => [null, null, null];
    }

    #[DataProvider('providerDisjunctions')]
    public function testEitherFollowsThreeValuedLogic(?bool $left, ?bool $right, ?bool $expected): void
    {
        self::assertSame($expected, Logic::either($left, $right));
    }

    /**
     * @return iterable<string, array{null|bool, null|bool, null|bool}>
     */
    public static function providerDisjunctions(): iterable
    {
        yield 'both false' => [false, false, false];

        yield 'one true' => [false, true, true];

        yield 'true settles it whatever the other side is' => [true, null, true];

        yield 'undecided spreads through a false side' => [false, null, null];

        yield 'undecided on both sides' => [null, null, null];
    }

    #[DataProvider('providerExclusiveDisjunctions')]
    public function testExclusiveFollowsThreeValuedLogic(?bool $left, ?bool $right, ?bool $expected): void
    {
        self::assertSame($expected, Logic::exclusive($left, $right));
    }

    /**
     * @return iterable<string, array{null|bool, null|bool, null|bool}>
     */
    public static function providerExclusiveDisjunctions(): iterable
    {
        yield 'exactly one of two decided values' => [true, false, true];

        yield 'both of two decided values' => [true, true, false];

        yield 'neither of two decided values' => [false, false, false];

        yield 'nothing can be said while the right is undecided' => [true, null, null];

        yield 'nothing can be said while the left is undecided' => [null, false, null];
    }

    public function testNegateReversesADecidedTruthValue(): void
    {
        self::assertFalse(Logic::negate(true));
    }

    public function testNegateLeavesUndecidedUndecided(): void
    {
        self::assertNull(Logic::negate(null));
    }

    /**
     * @throws GqlException
     */
    public function testHoldsKeepsARowAPredicateIsTrueOf(): void
    {
        self::assertTrue(Logic::holds(new BooleanDatum(true)));
    }

    /**
     * @throws GqlException
     */
    public function testHoldsDropsARowAPredicateCouldNotBeDecidedFor(): void
    {
        self::assertFalse(Logic::holds(new NullDatum()));
    }

    /**
     * @throws GqlException
     */
    public function testHoldsDropsARowAPredicateIsFalseOf(): void
    {
        self::assertFalse(Logic::holds(new BooleanDatum(false)));
    }
}
