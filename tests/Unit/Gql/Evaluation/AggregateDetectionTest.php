<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Evaluation;

use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\Evaluation\AggregateDetection;
use App\Gql\Invocation\AggregateCatalog;
use App\Gql\Syntax\Expression;
use App\Gql\Syntax\Expression\BinaryExpression;
use App\Gql\Syntax\Expression\BinaryOperator;
use App\Gql\Syntax\Expression\CallExpression;
use App\Gql\Syntax\Expression\CaseBranch;
use App\Gql\Syntax\Expression\CaseExpression;
use App\Gql\Syntax\Expression\ListExpression;
use App\Gql\Syntax\Expression\LiteralExpression;
use App\Gql\Syntax\Expression\PropertyExpression;
use App\Gql\Syntax\Expression\UnaryExpression;
use App\Gql\Syntax\Expression\UnaryOperator;
use App\Gql\Syntax\Expression\VariableExpression;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AggregateDetection::class)]
#[UsesClass(AggregateCatalog::class)]
#[UsesClass(BinaryExpression::class)]
#[UsesClass(BooleanDatum::class)]
#[UsesClass(CallExpression::class)]
#[UsesClass(CaseBranch::class)]
#[UsesClass(CaseExpression::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListExpression::class)]
#[UsesClass(LiteralExpression::class)]
#[UsesClass(PropertyExpression::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(UnaryExpression::class)]
#[UsesClass(VariableExpression::class)]
#[Small]
final class AggregateDetectionTest extends TestCase
{
    #[DataProvider('providerExpressionsAndWhetherTheySummarise')]
    public function testWithinReportsWhetherAnExpressionSummarisesRows(Expression $expression, bool $summarises): void
    {
        self::assertSame($summarises, AggregateDetection::within($expression));
    }

    /**
     * @return iterable<string, array{Expression, bool}>
     */
    public static function providerExpressionsAndWhetherTheySummarise(): iterable
    {
        yield 'count(*)' => [new CallExpression('count', star: true), true];

        yield 'p.name' => [new PropertyExpression(new VariableExpression('p'), 'name'), false];

        yield 'count(*) > 1' => [
            new BinaryExpression(
                BinaryOperator::Greater,
                new CallExpression('count', star: true),
                new LiteralExpression(new IntegerDatum(1)),
            ),
            true,
        ];

        yield "'found ' || count(*)" => [
            new BinaryExpression(
                BinaryOperator::Concatenate,
                new LiteralExpression(new StringDatum('found ')),
                new CallExpression('count', star: true),
            ),
            true,
        ];

        yield '-count(*)' => [new UnaryExpression(UnaryOperator::Negate, new CallExpression('count', star: true)), true];

        yield '[1, count(*)]' => [
            new ListExpression([new LiteralExpression(new IntegerDatum(1)), new CallExpression('count', star: true)]),
            true,
        ];

        yield 'collect_list(p).name' => [
            new PropertyExpression(new CallExpression('collect_list', [new VariableExpression('p')]), 'name'),
            true,
        ];

        yield 'size(collect_list(p))' => [
            new CallExpression('size', [new CallExpression('collect_list', [new VariableExpression('p')])]),
            true,
        ];

        yield "CASE WHEN count(*) > 1 THEN 'many' ELSE 'one' END" => [
            new CaseExpression(
                null,
                [
                    new CaseBranch(
                        new BinaryExpression(
                            BinaryOperator::Greater,
                            new CallExpression('count', star: true),
                            new LiteralExpression(new IntegerDatum(1)),
                        ),
                        new LiteralExpression(new StringDatum('many')),
                    ),
                ],
                new LiteralExpression(new StringDatum('one')),
            ),
            true,
        ];

        yield "CASE count(*) WHEN 1 THEN 'one' END" => [
            new CaseExpression(
                new CallExpression('count', star: true),
                [new CaseBranch(new LiteralExpression(new IntegerDatum(1)), new LiteralExpression(new StringDatum('one')))],
            ),
            true,
        ];

        yield "CASE WHEN TRUE THEN 'x' ELSE count(*) END" => [
            new CaseExpression(
                null,
                [new CaseBranch(new LiteralExpression(new BooleanDatum(true)), new LiteralExpression(new StringDatum('x')))],
                new CallExpression('count', star: true),
            ),
            true,
        ];

        yield 'CASE WHEN a THEN b ELSE c END' => [
            new CaseExpression(
                null,
                [new CaseBranch(new VariableExpression('a'), new VariableExpression('b'))],
                new VariableExpression('c'),
            ),
            false,
        ];

        yield "upper('a')" => [new CallExpression('upper', [new LiteralExpression(new StringDatum('a'))]), false];

        yield '42' => [new LiteralExpression(new IntegerDatum(42)), false];
    }

    public function testWithinPassesOverASummaryWrittenOverAGroupList(): void
    {
        $earliest = new CallExpression('min', [new PropertyExpression(new VariableExpression('e'), 'line')]);

        self::assertFalse(AggregateDetection::within($earliest, ['e']));
    }

    public function testWithinStillFindsASummaryWrittenOverSomethingElse(): void
    {
        $earliest = new CallExpression('min', [new PropertyExpression(new VariableExpression('p'), 'line')]);

        self::assertTrue(AggregateDetection::within($earliest, ['e']));
    }

    public function testWithinFindsTheOuterSummaryOfOneWrittenOverAGroupList(): void
    {
        $averaged = new CallExpression('avg', [
            new CallExpression('min', [new PropertyExpression(new VariableExpression('e'), 'line')]),
        ]);

        self::assertTrue(AggregateDetection::within($averaged, ['e']));
    }

    public function testWithinAnyFindsNoSummaryInNothingAtAll(): void
    {
        self::assertFalse(AggregateDetection::withinAny([]));
    }

    public function testWithinAnyFindsASummaryInAnyOneOfSeveralExpressions(): void
    {
        self::assertTrue(AggregateDetection::withinAny([
            new PropertyExpression(new VariableExpression('p'), 'name'),
            new CallExpression('count', star: true),
        ]));
    }

    public function testWithinAnyPassesOverASummaryWrittenOverAGroupList(): void
    {
        $expressions = [
            new CallExpression('size', [new VariableExpression('e')]),
            new CallExpression('max', [new VariableExpression('e')]),
        ];

        self::assertFalse(AggregateDetection::withinAny($expressions, ['e']));
    }

    public function testWithinCaseFindsNoSummaryInAChoiceThatReadsProperties(): void
    {
        $choice = new CaseExpression(null, [new CaseBranch(new VariableExpression('a'), new VariableExpression('b'))]);

        self::assertFalse(AggregateDetection::withinCase($choice));
    }

    public function testWithinCaseFindsASummaryInTheValueABranchTakes(): void
    {
        $choice = new CaseExpression(null, [new CaseBranch(new VariableExpression('a'), new CallExpression('count', star: true))]);

        self::assertTrue(AggregateDetection::withinCase($choice));
    }

    public function testAlongTheRowReadsASummaryOfAGroupListAsWrittenAlongTheRow(): void
    {
        $earliest = new CallExpression('min', [new PropertyExpression(new VariableExpression('e'), 'line')]);

        self::assertTrue(AggregateDetection::alongTheRow($earliest, ['e']));
    }

    public function testAlongTheRowReadsASummaryOfAnythingElseAsWrittenDownTheRows(): void
    {
        $earliest = new CallExpression('min', [new PropertyExpression(new VariableExpression('p'), 'line')]);

        self::assertFalse(AggregateDetection::alongTheRow($earliest, ['e']));
    }

    public function testAlongTheRowReadsACountOfTheRowsAsWrittenDownThem(): void
    {
        self::assertFalse(AggregateDetection::alongTheRow(new CallExpression('count', star: true), ['e']));
    }

    public function testAlongTheRowReadsASummaryOfSeveralThingsAsWrittenDownTheRows(): void
    {
        $summary = new CallExpression('min', [new VariableExpression('e'), new VariableExpression('f')]);

        self::assertFalse(AggregateDetection::alongTheRow($summary, ['e', 'f']));
    }

    public function testAlongTheRowReadsASummaryOfAComputedValueAsWrittenDownTheRows(): void
    {
        $summary = new CallExpression('min', [new CallExpression('size', [new VariableExpression('e')])]);

        self::assertFalse(AggregateDetection::alongTheRow($summary, ['e']));
    }

    public function testRootOfReadsANameAsItself(): void
    {
        self::assertSame('e', AggregateDetection::rootOf(new VariableExpression('e')));
    }

    public function testRootOfReadsAPropertyAsWhateverItsSubjectReadsFrom(): void
    {
        self::assertSame('e', AggregateDetection::rootOf(new PropertyExpression(new VariableExpression('e'), 'line')));
    }

    public function testRootOfReadsAnythingElseAsComingFromNoSingleName(): void
    {
        self::assertNull(AggregateDetection::rootOf(new CallExpression('count', star: true)));
    }
}
