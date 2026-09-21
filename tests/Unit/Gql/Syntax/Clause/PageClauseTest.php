<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax\Clause;

use App\Gql\Syntax\Clause\PageClause;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PageClause::class)]
#[Small]
final class PageClauseTest extends TestCase
{
    public function testAPageThatSaysNothingSkipsNothingAndKeepsEverything(): void
    {
        $page = new PageClause();

        self::assertSame(0, $page->offset);
        self::assertNull($page->limit);
    }

    public function testAPageCarriesHowManyRowsItSkipsAndKeeps(): void
    {
        $page = new PageClause(10, 5);

        self::assertSame(10, $page->offset);
        self::assertSame(5, $page->limit);
    }

    public function testAPageCanKeepNoRowsAtAll(): void
    {
        self::assertSame(0, (new PageClause(0, 0))->limit);
    }
}
