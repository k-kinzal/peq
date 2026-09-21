<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Datum;

use App\Gql\Datum\DatumIdentity;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DatumOrder;
use App\Gql\Datum\DecimalDatum;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\FloatDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\PathDatum;
use App\Gql\Datum\StringDatum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DatumIdentity::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DatumOrder::class)]
#[UsesClass(DecimalDatum::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(FloatDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(PathDatum::class)]
#[UsesClass(StringDatum::class)]
#[Small]
final class DatumIdentityTest extends TestCase
{
    public function testKeyIdentifiesAWholeNumberAndAnApproximateOneOfTheSameValueAsOneValue(): void
    {
        self::assertSame(DatumIdentity::key(new IntegerDatum(2)), DatumIdentity::key(new FloatDatum(2.0)));
    }

    public function testKeyIdentifiesAWholeNumberAndADecimalOfTheSameValueAsOneValue(): void
    {
        self::assertSame(DatumIdentity::key(new IntegerDatum(2)), DatumIdentity::key(new DecimalDatum(20, 1)));
    }

    public function testKeyIdentifiesADecimalAndAnApproximateNumberOfTheSameValueAsOneValue(): void
    {
        self::assertSame(DatumIdentity::key(new DecimalDatum(25, 1)), DatumIdentity::key(new FloatDatum(2.5)));
    }

    public function testKeyTellsTwoDifferentNumbersApart(): void
    {
        self::assertNotSame(DatumIdentity::key(new DecimalDatum(25, 1)), DatumIdentity::key(new DecimalDatum(26, 1)));
    }

    public function testKeyTellsANumberApartFromTheStringSpellingIt(): void
    {
        self::assertNotSame(DatumIdentity::key(new IntegerDatum(2)), DatumIdentity::key(new StringDatum('2')));
    }

    public function testKeyIdentifiesTwoAbsentValuesAsOneValueWhichGroupingNeeds(): void
    {
        self::assertSame(DatumIdentity::key(new NullDatum()), DatumIdentity::key(new NullDatum()));
    }

    public function testKeyIdentifiesTwoReferencesToTheSameSymbolAsOneValue(): void
    {
        self::assertSame(DatumIdentity::key(new NodeDatum('a', ['Class'])), DatumIdentity::key(new NodeDatum('a')));
    }

    public function testKeyTellsASymbolApartFromARelationWithTheSameIdentity(): void
    {
        self::assertNotSame(DatumIdentity::key(new NodeDatum('e')), DatumIdentity::key(new EdgeDatum('e', [], [], 'a', 'b')));
    }

    public function testKeyIdentifiesTwoListsOfEqualNumbersAsOneValue(): void
    {
        self::assertSame(
            DatumIdentity::key(new ListDatum([new IntegerDatum(1)])),
            DatumIdentity::key(new ListDatum([new DecimalDatum(10, 1)])),
        );
    }

    public function testKeyIdentifiesTwoEqualPathsAsOneValue(): void
    {
        self::assertSame(
            DatumIdentity::key(new PathDatum([new NodeDatum('a')])),
            DatumIdentity::key(new PathDatum([new NodeDatum('a')])),
        );
    }

    public function testKeyTellsAListApartFromAPathOfTheSameElements(): void
    {
        self::assertNotSame(
            DatumIdentity::key(new ListDatum([new NodeDatum('a')])),
            DatumIdentity::key(new PathDatum([new NodeDatum('a')])),
        );
    }

    public function testNumberIdentifiesAnApproximateWholeNumberAsTheWholeNumberItEquals(): void
    {
        self::assertSame('2', DatumIdentity::number(new FloatDatum(2.0)));
    }

    public function testNumberKeepsTheFractionalPartOfAnApproximateNumber(): void
    {
        self::assertSame('2.5', DatumIdentity::number(new FloatDatum(2.5)));
    }

    public function testNumberDropsTheZerosADecimalWasWrittenWith(): void
    {
        self::assertSame('1.5', DatumIdentity::number(new DecimalDatum(150, 2)));
    }

    public function testNumberIdentifiesADecimalThatIsWholeAsTheWholeNumberItEquals(): void
    {
        self::assertSame('20', DatumIdentity::number(new DecimalDatum(200, 1)));
    }

    public function testNumberIdentifiesADecimalZeroAsZero(): void
    {
        self::assertSame('0', DatumIdentity::number(new DecimalDatum(0, 2)));
    }

    public function testNumberKeepsTheSignOfANegativeDecimal(): void
    {
        self::assertSame('-0.5', DatumIdentity::number(new DecimalDatum(-5, 1)));
    }

    public function testNumberWritesAWholeNumberAsItself(): void
    {
        self::assertSame('-3', DatumIdentity::number(new IntegerDatum(-3)));
    }

    public function testNumberWritesANumberBeyondEveryNumberAsItself(): void
    {
        self::assertSame('INF', DatumIdentity::number(new FloatDatum(INF)));
    }

    public function testNumberGroupsEveryUndefinedResultTogether(): void
    {
        self::assertSame('NAN', DatumIdentity::number(new FloatDatum(NAN)));
    }

    public function testKeyOfAllPutsEveryRowInOneGroupWhenThereIsNothingToGroupBy(): void
    {
        self::assertSame('', DatumIdentity::keyOfAll([]));
    }

    public function testKeyOfAllTellsTwoGroupsApartByAnyOfTheirValues(): void
    {
        self::assertNotSame(
            DatumIdentity::keyOfAll([new StringDatum('a'), new IntegerDatum(1)]),
            DatumIdentity::keyOfAll([new StringDatum('a'), new IntegerDatum(2)]),
        );
    }

    public function testKeyOfAllPutsRowsOfEqualValuesInOneGroup(): void
    {
        self::assertSame(
            DatumIdentity::keyOfAll([new StringDatum('a'), new IntegerDatum(1)]),
            DatumIdentity::keyOfAll([new StringDatum('a'), new DecimalDatum(10, 1)]),
        );
    }

    public function testPlainWritesAnExponentOut(): void
    {
        self::assertSame('0.00000015', DatumIdentity::plain('1.5E-7'));
    }

    public function testPlainLeavesOutTrailingZerosAndABarePoint(): void
    {
        self::assertSame('3', DatumIdentity::plain('3.0'));
    }

    public function testPlainWritesNegativeZeroAsZero(): void
    {
        self::assertSame('0', DatumIdentity::plain('-0.0'));
    }

    public function testPlainWritesALargeExponentOutWithZeros(): void
    {
        self::assertSame('-12500', DatumIdentity::plain('-1.25E+4'));
    }

    public function testKeyTellsAnApproximateSumFromTheExactNumberItMissed(): void
    {
        self::assertNotSame(DatumIdentity::key(new FloatDatum(0.1 + 0.2)), DatumIdentity::key(new DecimalDatum(3, 1)));
    }

    public function testKeyGroupsAnApproximateNumberWithTheExactNumberItEquals(): void
    {
        self::assertSame(DatumIdentity::key(new FloatDatum(0.1)), DatumIdentity::key(new DecimalDatum(1, 1)));
    }
}
