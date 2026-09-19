<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Datum;

use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumJson;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\FloatDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\PathDatum;
use App\Gql\Datum\StringDatum;
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
#[UsesClass(DatumKind::class)]
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

        yield 'an approximate number' => [new FloatDatum(1.5), '1.5'];

        yield 'an approximate number that came out whole' => [new FloatDatum(2.0), '2.0'];

        yield 'a string' => [new StringDatum('App\Invoice'), '"App\\\Invoice"'];

        yield 'a list' => [new ListDatum([new IntegerDatum(1)]), '[1]'];

        yield 'a list of nothing' => [new ListDatum([]), '[]'];
    }

    public function testOfWritesASymbolAsAnObjectCarryingWhatAQuerySelectedItBy(): void
    {
        $node = new NodeDatum('App\Invoice', ['Class'], []);

        self::assertSame('{"id":"App\\\Invoice","labels":["Class"],"properties":{}}', DatumJson::of($node));
    }

    public function testElementWritesARelationWithBothOfItsEnds(): void
    {
        $edge = new EdgeDatum('e', ['calls'], [], 'a', 'b');

        self::assertSame(
            '{"id":"e","labels":["calls"],"origin":"a","target":"b","properties":{}}',
            DatumJson::element($edge),
        );
    }

    public function testElementWritesAPathAsTheChainItIs(): void
    {
        $path = PathDatum::at(new NodeDatum('a'))
            ->continuedBy(new EdgeDatum('e', [], [], 'a', 'b'), new NodeDatum('b'))
        ;

        self::assertStringStartsWith('[{"id":"a"', DatumJson::element($path));
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
        self::assertSame('{"line":12}', DatumJson::properties(['line' => new IntegerDatum(12)]));
    }

    public function testTextEscapesANamespaceSeparatorBecauseJsonRequiresIt(): void
    {
        self::assertSame('"App\\\Invoice"', DatumJson::text('App\Invoice'));
    }

    public function testTextLeavesAPathSeparatorAloneBecauseJsonDoesNotRequireIt(): void
    {
        self::assertSame('"src/Invoice.php"', DatumJson::text('src/Invoice.php'));
    }

    public function testNumberWritesAnOrdinaryNumberAsItself(): void
    {
        self::assertSame('1.5', DatumJson::number(1.5));
    }

    public function testNumberWritesOneJsonCannotWriteAsAnAbsence(): void
    {
        self::assertSame('null', DatumJson::number(INF));
    }
}
