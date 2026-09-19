<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Datum;

use App\Gql\Datum\DatumIdentity;
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
    public function testKeyIdentifiesTwoWaysOfWritingTheSameNumberAsOneValue(): void
    {
        self::assertSame(DatumIdentity::key(new IntegerDatum(2)), DatumIdentity::key(new FloatDatum(2.0)));
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
        self::assertSame(
            DatumIdentity::key(new NodeDatum('a', ['Class'])),
            DatumIdentity::key(new NodeDatum('a')),
        );
    }

    public function testKeyTellsASymbolApartFromARelationWithTheSameIdentity(): void
    {
        self::assertNotSame(
            DatumIdentity::key(new NodeDatum('e')),
            DatumIdentity::key(new EdgeDatum('e', [], [], 'a', 'b')),
        );
    }

    public function testKeyIdentifiesTwoEqualListsAsOneValue(): void
    {
        self::assertSame(
            DatumIdentity::key(new ListDatum([new IntegerDatum(1)])),
            DatumIdentity::key(new ListDatum([new IntegerDatum(1)])),
        );
    }

    public function testKeyIdentifiesTwoEqualPathsAsOneValue(): void
    {
        self::assertSame(
            DatumIdentity::key(PathDatum::at(new NodeDatum('a'))),
            DatumIdentity::key(PathDatum::at(new NodeDatum('a'))),
        );
    }

    public function testNumberIdentifiesAnApproximateWholeNumberAsTheWholeNumberItEquals(): void
    {
        self::assertSame('2', DatumIdentity::number(new FloatDatum(2.0)));
    }

    public function testNumberKeepsAFractionalPart(): void
    {
        self::assertSame('2.5', DatumIdentity::number(new FloatDatum(2.5)));
    }

    public function testNumberWritesANumberThatIsNotOneAsItself(): void
    {
        self::assertSame('INF', DatumIdentity::number(new FloatDatum(INF)));
    }

    public function testNumberGroupsEveryUndefinedResultTogether(): void
    {
        self::assertSame(DatumIdentity::number(new FloatDatum(NAN)), DatumIdentity::number(new FloatDatum(NAN)));
    }

    public function testKeyOfAllPutsEveryRowInOneGroupWhenThereIsNothingToGroupBy(): void
    {
        self::assertSame('', DatumIdentity::keyOfAll([]));
    }

    public function testKeyOfAllTellsTwoGroupsApartByAnyOfTheirValues(): void
    {
        $left = [new StringDatum('a'), new IntegerDatum(1)];
        $right = [new StringDatum('a'), new IntegerDatum(2)];

        self::assertNotSame(DatumIdentity::keyOfAll($left), DatumIdentity::keyOfAll($right));
    }
}
