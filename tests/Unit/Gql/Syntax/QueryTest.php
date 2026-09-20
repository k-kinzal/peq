<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax;

use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\DatumKind;
use App\Gql\Syntax\Clause\FilterClause;
use App\Gql\Syntax\Clause\ReturnClause;
use App\Gql\Syntax\Expression\LiteralExpression;
use App\Gql\Syntax\Query;
use App\Gql\Syntax\QueryBlock;
use App\Gql\Syntax\SetOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Query::class)]
#[UsesClass(QueryBlock::class)]
#[UsesClass(FilterClause::class)]
#[UsesClass(LiteralExpression::class)]
#[UsesClass(BooleanDatum::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(SetOperator::class)]
#[Small]
final class QueryTest extends TestCase
{
    #[DataProvider('providerOneBlock')]
    public function testAQueryCarriesTheBlocksItIsWrittenFrom(QueryBlock $block): void
    {
        self::assertSame([$block], (new Query([$block]))->blocks);
    }

    #[DataProvider('providerOneBlock')]
    public function testAQueryCombiningNothingHasNoOperators(QueryBlock $block): void
    {
        self::assertSame([], (new Query([$block]))->operators);
    }

    #[DataProvider('providerOneBlock')]
    public function testAQueryCarriesOneOperatorForEachBlockAfterTheFirst(QueryBlock $block): void
    {
        $parsed = new Query([$block, $block], [SetOperator::UnionAll]);

        self::assertCount(1, $parsed->operators);
        self::assertCount(2, $parsed->blocks);
    }

    #[DataProvider('providerOneBlock')]
    public function testOfIsTheQueryOfOneBlockThatCombinesNothing(QueryBlock $block): void
    {
        self::assertCount(0, Query::of($block)->operators);
    }

    /**
     * @return iterable<string, array{QueryBlock}>
     */
    public static function providerOneBlock(): iterable
    {
        yield 'a block that shows every name it bound' => [
            new QueryBlock([new ReturnClause()]),
        ];

        yield 'a block that keeps every row and then shows it' => [
            new QueryBlock([
                new FilterClause(new LiteralExpression(new BooleanDatum(true))),
                new ReturnClause(),
            ]),
        ];
    }
}
