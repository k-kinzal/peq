<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax;

use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\DatumKind;
use App\Gql\Syntax\Clause\FilterClause;
use App\Gql\Syntax\Expression\LiteralExpression;
use App\Gql\Syntax\QueryBlock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(QueryBlock::class)]
#[UsesClass(FilterClause::class)]
#[UsesClass(LiteralExpression::class)]
#[UsesClass(BooleanDatum::class)]
#[UsesClass(DatumKind::class)]
#[Small]
final class QueryBlockTest extends TestCase
{
    public function testABlockCarriesItsClausesInTheOrderTheyRun(): void
    {
        $clause = new FilterClause(new LiteralExpression(new BooleanDatum(true)));

        self::assertSame([$clause], (new QueryBlock([$clause]))->clauses);
    }
}
