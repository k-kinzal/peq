<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax\Clause;

use App\Gql\Syntax\Clause\OrderByClause;
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
#[CoversClass(OrderByClause::class)]
#[UsesClass(SortDirection::class)]
#[UsesClass(SortKey::class)]
#[UsesClass(VariableExpression::class)]
#[Small]
final class OrderByClauseTest extends TestCase
{
    public function testAnOrderingCarriesItsKeysMostSignificantFirst(): void
    {
        $first = new SortKey(new VariableExpression('a'));
        $second = new SortKey(new VariableExpression('b'));

        self::assertSame([$first, $second], (new OrderByClause([$first, $second]))->keys);
    }
}
