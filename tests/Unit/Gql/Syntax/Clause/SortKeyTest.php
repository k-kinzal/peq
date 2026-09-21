<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax\Clause;

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
#[CoversClass(SortKey::class)]
#[UsesClass(SortDirection::class)]
#[UsesClass(VariableExpression::class)]
#[Small]
final class SortKeyTest extends TestCase
{
    public function testAKeyCarriesWhatToOrderBy(): void
    {
        $value = new VariableExpression('name');

        self::assertSame($value, (new SortKey($value))->value);
    }

    public function testAKeyThatSaysNothingOrdersSmallestFirst(): void
    {
        self::assertSame(SortDirection::Ascending, (new SortKey(new VariableExpression('name')))->direction);
    }

    public function testAKeyCarriesTheDirectionItWasGiven(): void
    {
        $key = new SortKey(new VariableExpression('name'), SortDirection::Descending);

        self::assertSame(SortDirection::Descending, $key->direction);
    }
}
