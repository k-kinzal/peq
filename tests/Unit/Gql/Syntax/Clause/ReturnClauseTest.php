<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax\Clause;

use App\Gql\Syntax\Clause\PageClause;
use App\Gql\Syntax\Clause\Projection;
use App\Gql\Syntax\Clause\ReturnClause;
use App\Gql\Syntax\Clause\SortDirection;
use App\Gql\Syntax\Clause\SortKey;
use App\Gql\Syntax\Expression\VariableExpression;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ReturnClause::class)]
#[UsesClass(PageClause::class)]
#[UsesClass(Projection::class)]
#[UsesClass(SortKey::class)]
#[UsesClass(SortDirection::class)]
#[UsesClass(VariableExpression::class)]
#[Small]
final class ReturnClauseTest extends TestCase
{
    public function testEverythingReportsAProjectionThatNamesNoColumn(): void
    {
        self::assertTrue((new ReturnClause())->everything());
    }

    public function testAProjectionThatNamesAColumnAsksForThatOne(): void
    {
        $column = new Projection(new VariableExpression('p'), null, 'p');

        self::assertFalse((new ReturnClause([$column]))->everything());
        self::assertSame([$column], (new ReturnClause([$column]))->columns);
    }

    public function testAProjectionCarriesWhetherRowsThatRepeatAreShownOnce(): void
    {
        self::assertTrue((new ReturnClause([], true))->distinct);
    }

    public function testAProjectionCarriesWhatItGroupsByAndHowItOrdersAndPages(): void
    {
        $key = new VariableExpression('kind');
        $order = new SortKey(new VariableExpression('n'), SortDirection::Descending);
        $page = new PageClause(0, 10);
        $clause = new ReturnClause([], false, [$key], [$order], $page);

        self::assertSame([$key], $clause->groupBy);
        self::assertSame([$order], $clause->orderBy);
        self::assertSame($page, $clause->page);
    }

    public function testAProjectionThatSaysNothingAboutPagingShowsAllOfIt(): void
    {
        self::assertNull((new ReturnClause())->page);
    }
}
