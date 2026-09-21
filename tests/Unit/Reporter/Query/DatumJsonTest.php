<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Query;

use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\DateTimeDatum;
use App\Gql\Datum\Datum;
use App\Gql\Datum\DecimalDatum;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\FloatDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\PathDatum;
use App\Gql\Datum\StringDatum;
use App\Reporter\Query\DatumJson;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DatumJson::class)]
#[UsesClass(BooleanDatum::class)]
#[UsesClass(DateTimeDatum::class)]
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
final class DatumJsonTest extends TestCase
{
    #[DataProvider('providerValuesAndTheirJson')]
    public function testOfWritesAValueAsJson(Datum $value, string $written): void
    {
        self::assertSame($written, DatumJson::of($value));
    }

    /**
     * @return iterable<string, array{Datum, string}>
     */
    public static function providerValuesAndTheirJson(): iterable
    {
        yield 'the absence of a value' => [new NullDatum(), 'null'];

        yield 'truth' => [new BooleanDatum(true), 'true'];

        yield 'its opposite' => [new BooleanDatum(false), 'false'];

        yield 'a whole number' => [new IntegerDatum(42), '42'];

        yield 'a decimal, with exactly the digits it has' => [new DecimalDatum(150, 2), '1.50'];

        yield 'a negative decimal smaller than one' => [new DecimalDatum(-5, 2), '-0.05'];

        yield 'an approximate number' => [new FloatDatum(1.5), '1.5'];

        yield 'an approximate number that came out whole' => [new FloatDatum(2.0), '2.0'];

        yield 'an approximate number that is not a number at all' => [new FloatDatum(NAN), 'null'];

        yield 'a string' => [new StringDatum('App\Invoice'), '"App\\\Invoice"'];

        yield 'a list' => [new ListDatum([new IntegerDatum(1), new NullDatum()]), '[1,null]'];

        yield 'a list of nothing' => [new ListDatum([]), '[]'];

        yield 'a symbol' => [new NodeDatum('App\Invoice', ['Class'], []), '{"id":"App\\\Invoice","labels":["Class"],"properties":{}}'];

        yield 'a moment' => [new DateTimeDatum(new DateTimeImmutable('2024-01-15T10:30:00+09:00')), '"2024-01-15T10:30:00+09:00"'];
    }

    public function testElementWritesASymbolAsAnObjectCarryingWhatAQuerySelectedItBy(): void
    {
        $node = new NodeDatum('App\Invoice', ['Class', 'ClassLike'], ['name' => new StringDatum('Invoice')]);

        self::assertSame(
            '{"id":"App\\\Invoice","labels":["Class","ClassLike"],"properties":{"name":"Invoice"}}',
            DatumJson::element($node),
        );
    }

    public function testElementWritesARelationWithBothOfItsEnds(): void
    {
        $edge = new EdgeDatum('e', ['calls'], ['line' => new IntegerDatum(12)], 'a', 'b');

        self::assertSame(
            '{"id":"e","labels":["calls"],"origin":"a","target":"b","properties":{"line":12}}',
            DatumJson::element($edge),
        );
    }

    public function testElementWritesAPathAsTheChainItIs(): void
    {
        $path = new PathDatum([new NodeDatum('a'), new EdgeDatum('e', [], [], 'a', 'b'), new NodeDatum('b')]);

        self::assertSame(
            '[{"id":"a","labels":[],"properties":{}},'
            .'{"id":"e","labels":[],"origin":"a","target":"b","properties":{}},'
            .'{"id":"b","labels":[],"properties":{}}]',
            DatumJson::element($path),
        );
    }

    public function testElementWritesAnythingWithNoShapeOfItsOwnAsItsText(): void
    {
        self::assertSame('"x"', DatumJson::element(new StringDatum('x')));
    }

    public function testPropertiesWritesAnElementThatCarriesNothingAsAnObject(): void
    {
        self::assertSame('{}', DatumJson::properties([]));
    }

    public function testPropertiesWritesEachPropertyUnderItsName(): void
    {
        self::assertSame(
            '{"line":12,"weight":0.25}',
            DatumJson::properties(['line' => new IntegerDatum(12), 'weight' => new DecimalDatum(25, 2)]),
        );
    }

    public function testTextEscapesANamespaceSeparatorBecauseJsonRequiresIt(): void
    {
        self::assertSame('"App\\\Invoice"', DatumJson::text('App\Invoice'));
    }

    public function testTextLeavesAPathSeparatorAloneBecauseJsonDoesNotRequireIt(): void
    {
        self::assertSame('"src/Invoice.php"', DatumJson::text('src/Invoice.php'));
    }

    public function testTextLeavesCharactersBeyondAsciiAsTheyAre(): void
    {
        self::assertSame('"Café"', DatumJson::text('Café'));
    }

    public function testNumberWritesAnOrdinaryNumberAsItself(): void
    {
        self::assertSame('1.5', DatumJson::number(1.5));
    }

    public function testNumberKeepsTheFractionOfANumberThatCameOutWhole(): void
    {
        self::assertSame('2.0', DatumJson::number(2.0));
    }

    public function testNumberWritesOneJsonCannotWriteAsAnAbsence(): void
    {
        self::assertSame('null', DatumJson::number(INF));
    }
}
