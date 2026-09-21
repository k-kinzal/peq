<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax\Expression;

use App\Gql\Syntax\Expression\UnaryExpression;
use App\Gql\Syntax\Expression\UnaryOperator;
use App\Gql\Syntax\Expression\VariableExpression;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(UnaryExpression::class)]
#[UsesClass(UnaryOperator::class)]
#[UsesClass(VariableExpression::class)]
#[Small]
final class UnaryExpressionTest extends TestCase
{
    public function testAUnaryExpressionCarriesWhatIsAppliedAndWhatItIsAppliedTo(): void
    {
        $operand = new VariableExpression('p');
        $written = new UnaryExpression(UnaryOperator::Not, $operand);

        self::assertSame(UnaryOperator::Not, $written->operator);
        self::assertSame($operand, $written->operand);
    }
}
