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
use App\Gql\Invocation\FunctionCatalog;
use App\Gql\Invocation\GeneralFunctions;
use App\Gql\Invocation\GraphFunctions;
use App\Gql\Invocation\ListFunctions;
use App\Gql\Invocation\TextFunctions;
use App\Gql\StatusCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(FunctionCatalog::class)]
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
#[UsesClass(GeneralFunctions::class)]
#[UsesClass(GraphFunctions::class)]
#[UsesClass(ListFunctions::class)]
#[UsesClass(TextFunctions::class)]
#[Small]
final class FunctionCatalogTest extends TestCase
{
    /**
     * @param list<Datum> $arguments
     *
     * @throws GqlException
     */
    #[DataProvider('providerCallsAndWhatTheyProduce')]
    public function testCallAppliesTheFunctionTheQueryAsksForByName(string $name, array $arguments, string $expected): void
    {
        self::assertSame($expected, FunctionCatalog::call($name, $arguments)->toText());
    }

    /**
     * @return iterable<string, array{string, list<Datum>, string}>
     */
    public static function providerCallsAndWhatTheyProduce(): iterable
    {
        $path = PathDatum::at(new NodeDatum('a'))
            ->continuedBy(new EdgeDatum('e', ['calls'], [], 'a', 'b'), new NodeDatum('b'))
        ;

        yield 'the length of a string' => ['char_length', [new StringDatum('abc')], '3'];

        yield 'a string in upper case' => ['upper', [new StringDatum('a')], 'A'];

        yield 'a string in lower case' => ['lower', [new StringDatum('A')], 'a'];

        yield 'a string without its whitespace' => ['trim', [new StringDatum(' a ')], 'a'];

        yield 'a list cut to a length' => ['trim', [new ListDatum([new IntegerDatum(1), new IntegerDatum(2)]), new IntegerDatum(1)], '[1]'];

        yield 'the values of a list written out' => ['string_join', [new ListDatum([new StringDatum('a')]), new StringDatum(',')], 'a'];

        yield 'the size of a list' => ['size', [new ListDatum([new IntegerDatum(1)])], '1'];

        yield 'the symbols of a path' => ['nodes', [$path], '[a, b]'];

        yield 'the relations of a path' => ['edges', [$path], '[a -[calls]-> b]'];

        yield 'everything a path is made of' => ['elements', [$path], '[a, a -[calls]-> b, b]'];

        yield 'the labels of a symbol' => ['labels', [new NodeDatum('a', ['Class'])], '[Class]'];

        yield 'the length of a path' => ['path_length', [$path], '1'];

        yield 'a value written as JSON' => ['to_json_string', [new IntegerDatum(1)], '1'];

        yield 'the first value that is there' => ['coalesce', [new NullDatum(), new StringDatum('b')], 'b'];

        yield 'a value withdrawn when it equals another' => ['nullif', [new StringDatum('a'), new StringDatum('b')], 'a'];

        yield 'a moment written in ISO 8601' => ['zoned_datetime', [new StringDatum('2024-01-15T10:30:00+00:00')], '2024-01-15T10:30:00+00:00'];
    }

    /**
     * @throws GqlException
     */
    public function testCallRecognisesAFunctionHoweverItIsCased(): void
    {
        self::assertSame('A', FunctionCatalog::call('UPPER', [new StringDatum('a')])->toText());
    }

    /**
     * @throws GqlException
     */
    public function testCallReportsANameThatBelongsToNoFunction(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('there is no function called "sqrt"');

        FunctionCatalog::call('sqrt', []);
    }

    /**
     * @throws GqlException
     */
    public function testCallReportsACallGivenTheWrongNumberOfArguments(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('upper takes 1 argument(s), and was given 0');

        FunctionCatalog::call('upper', []);
    }

    /**
     * @throws GqlException
     */
    public function testTextTrimsAStringWhenGivenOnlyAString(): void
    {
        self::assertSame('a', FunctionCatalog::text('trim', [new StringDatum(' a ')])->toText());
    }

    /**
     * @throws GqlException
     */
    public function testTextCutsAListWhenGivenALengthAsWell(): void
    {
        $rows = new ListDatum([new IntegerDatum(1), new IntegerDatum(2)]);

        self::assertSame('[1]', FunctionCatalog::text('trim', [$rows, new IntegerDatum(1)])->toText());
    }

    /**
     * @throws GqlException
     */
    public function testArgumentReturnsAnArgumentWhenTheCountIsRight(): void
    {
        self::assertSame('a', FunctionCatalog::argument('upper', [new StringDatum('a')], 0, 1)->toText());
    }

    /**
     * @throws GqlException
     */
    public function testArgumentReportsACallGivenTheWrongNumberOfArguments(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('upper takes 1 argument(s), and was given 0');

        FunctionCatalog::argument('upper', [], 0, 1);
    }

    /**
     * @throws GqlException
     */
    public function testAtLeastOneAnswersWithTheFirstValueThatIsThere(): void
    {
        self::assertSame('b', FunctionCatalog::atLeastOne('coalesce', [new NullDatum(), new StringDatum('b')])->toText());
    }

    /**
     * @throws GqlException
     */
    public function testAtLeastOneReportsACallGivenNothingToChooseBetween(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('coalesce takes at least one argument, and was given none');

        FunctionCatalog::atLeastOne('coalesce', []);
    }

    /**
     * @throws GqlException
     */
    public function testMomentAnswersWithNowWhenAskedForNothing(): void
    {
        self::assertSame(DatumKind::DateTime, FunctionCatalog::moment('zoned_datetime', [], 0)->kind());
    }

    /**
     * @throws GqlException
     */
    public function testMomentReportsACallGivenMoreThanItTakes(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('zoned_datetime takes at most one argument, and was given 2');

        FunctionCatalog::moment('zoned_datetime', [new StringDatum('a'), new StringDatum('b')], 2);
    }

    public function testAllNamesOnlyFunctionsThisTestActuallyCalls(): void
    {
        $called = array_column([...self::providerCallsAndWhatTheyProduce()], 0);

        self::assertSame([], array_values(array_diff(FunctionCatalog::all(), $called)));
    }

    public function testAllOffersTheFunctionsThatTakeAPathApart(): void
    {
        self::assertContains('path_length', FunctionCatalog::all());
    }
}
