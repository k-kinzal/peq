<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Invocation;

use App\Gql\Argument\NumberArgument;
use App\Gql\Argument\TextArgument;
use App\Gql\Datum\DateTimeDatum;
use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DatumOrder;
use App\Gql\Datum\EdgeDatum;
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
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(FunctionCatalog::class)]
#[UsesClass(DateTimeDatum::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DatumOrder::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(GeneralFunctions::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(GraphFunctions::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(ListFunctions::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(NumberArgument::class)]
#[UsesClass(PathDatum::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(TextArgument::class)]
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
    public function testCallAppliesTheFunctionTheQueryAsksForByName(string $name, array $arguments, Datum $expected): void
    {
        self::assertEquals($expected, FunctionCatalog::call($name, $arguments));
    }

    /**
     * @return iterable<string, array{string, list<Datum>, Datum}>
     */
    public static function providerCallsAndWhatTheyProduce(): iterable
    {
        yield 'the length of a string' => ['char_length', [new StringDatum('abc')], new IntegerDatum(3)];

        yield 'a string in upper case' => ['upper', [new StringDatum('a')], new StringDatum('A')];

        yield 'a string in lower case' => ['lower', [new StringDatum('A')], new StringDatum('a')];

        yield 'a string without its whitespace' => ['trim', [new StringDatum(' a ')], new StringDatum('a')];

        yield 'a list cut to a length' => [
            'trim',
            [new ListDatum([new IntegerDatum(1), new IntegerDatum(2)]), new IntegerDatum(1)],
            new ListDatum([new IntegerDatum(1)]),
        ];

        yield 'the first characters of a string' => ['left', [new StringDatum('abc'), new IntegerDatum(2)], new StringDatum('ab')];

        yield 'the last characters of a string' => ['right', [new StringDatum('abc'), new IntegerDatum(2)], new StringDatum('bc')];

        yield 'the size of a list' => ['size', [new ListDatum([new IntegerDatum(1)])], new IntegerDatum(1)];

        yield 'everything a path is made of' => [
            'elements',
            [new PathDatum([new NodeDatum('a'), new EdgeDatum('e', ['calls'], [], 'a', 'b'), new NodeDatum('b')])],
            new ListDatum([new NodeDatum('a'), new EdgeDatum('e', ['calls'], [], 'a', 'b'), new NodeDatum('b')]),
        ];

        yield 'the length of a path' => [
            'path_length',
            [new PathDatum([new NodeDatum('a'), new EdgeDatum('e', ['calls'], [], 'a', 'b'), new NodeDatum('b')])],
            new IntegerDatum(1),
        ];

        yield 'the first value that is there' => ['coalesce', [new NullDatum(), new StringDatum('b')], new StringDatum('b')];

        yield 'a value withdrawn when it equals another' => ['nullif', [new StringDatum('a'), new StringDatum('a')], new NullDatum()];

        yield 'a moment written in ISO 8601' => [
            'zoned_datetime',
            [new StringDatum('2024-01-15T10:30:00+00:00')],
            new DateTimeDatum(new DateTimeImmutable('2024-01-15T10:30:00+00:00')),
        ];

        yield 'a function asked for in capitals' => ['UPPER', [new StringDatum('a')], new StringDatum('A')];
    }

    /**
     * @throws GqlException
     */
    public function testCallReportsANameThatBelongsToNoFunction(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[42000] error: syntax error or access rule violation: there is no function called "sqrt"');

        FunctionCatalog::call('sqrt', []);
    }

    /**
     * @throws GqlException
     */
    public function testCallReportsACallGivenTheWrongNumberOfArguments(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[42001] error: syntax error or access rule violation - invalid syntax: upper takes 1 argument(s), and was given 0');

        FunctionCatalog::call('upper', []);
    }

    /**
     * @throws GqlException
     */
    public function testTextTrimsAStringWhenGivenOnlyAString(): void
    {
        self::assertEquals(new StringDatum('a'), FunctionCatalog::text('trim', [new StringDatum(' a ')]));
    }

    /**
     * @throws GqlException
     */
    public function testTextCutsAListWhenGivenALengthAsWell(): void
    {
        $rows = new ListDatum([new IntegerDatum(1), new IntegerDatum(2)]);

        self::assertEquals(new ListDatum([new IntegerDatum(1)]), FunctionCatalog::text('trim', [$rows, new IntegerDatum(1)]));
    }

    /**
     * @throws GqlException
     */
    public function testTextReportsAStringFunctionGivenTheWrongNumberOfArguments(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[42001] error: syntax error or access rule violation - invalid syntax: char_length takes 1 argument(s), and was given 2');

        FunctionCatalog::text('char_length', [new StringDatum('a'), new StringDatum('b')]);
    }

    /**
     * @throws GqlException
     */
    public function testArgumentReturnsAnArgumentWhenTheCountIsRight(): void
    {
        self::assertEquals(new StringDatum('b'), FunctionCatalog::argument('left', [new StringDatum('a'), new StringDatum('b')], 1, 2));
    }

    /**
     * @throws GqlException
     */
    public function testArgumentReportsACallGivenTheWrongNumberOfArguments(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[42001] error: syntax error or access rule violation - invalid syntax: upper takes 1 argument(s), and was given 0');

        FunctionCatalog::argument('upper', [], 0, 1);
    }

    /**
     * @throws GqlException
     */
    public function testAtLeastOneAnswersWithTheFirstValueThatIsThere(): void
    {
        self::assertEquals(new StringDatum('b'), FunctionCatalog::atLeastOne('coalesce', [new NullDatum(), new StringDatum('b')]));
    }

    /**
     * @throws GqlException
     */
    public function testAtLeastOneReportsACallGivenNothingToChooseBetween(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[42001] error: syntax error or access rule violation - invalid syntax: coalesce takes at least one argument, and was given none');

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
    public function testMomentReadsTheMomentItIsGiven(): void
    {
        self::assertEquals(
            new DateTimeDatum(new DateTimeImmutable('2024-01-15T10:30:00+00:00')),
            FunctionCatalog::moment('zoned_datetime', [new StringDatum('2024-01-15T10:30:00+00:00')], 1),
        );
    }

    /**
     * @throws GqlException
     */
    public function testMomentReportsACallGivenMoreThanItTakes(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[42001] error: syntax error or access rule violation - invalid syntax: zoned_datetime takes at most one argument, and was given 2');

        FunctionCatalog::moment('zoned_datetime', [new StringDatum('a'), new StringDatum('b')], 2);
    }

    public function testAllNamesEveryFunctionAQueryCanCall(): void
    {
        self::assertSame(
            ['char_length', 'upper', 'lower', 'trim', 'left', 'right', 'size', 'elements', 'path_length', 'coalesce', 'nullif', 'zoned_datetime'],
            FunctionCatalog::all(),
        );
    }
}
