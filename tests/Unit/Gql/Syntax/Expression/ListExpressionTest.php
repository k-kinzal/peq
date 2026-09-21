<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax\Expression;

use App\Gql\Syntax\Expression\ListExpression;
use App\Gql\Syntax\Expression\VariableExpression;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ListExpression::class)]
#[UsesClass(VariableExpression::class)]
#[Small]
final class ListExpressionTest extends TestCase
{
    public function testAListCarriesItsValuesInTheOrderTheyWereWritten(): void
    {
        $first = new VariableExpression('a');
        $second = new VariableExpression('b');

        self::assertSame([$first, $second], (new ListExpression([$first, $second]))->items);
    }

    public function testAListCanHoldNothing(): void
    {
        self::assertSame([], (new ListExpression())->items);
    }
}
