<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax\Expression;

use App\Gql\Syntax\Expression\BinaryExpression;
use App\Gql\Syntax\Expression\BinaryOperator;
use App\Gql\Syntax\Expression\VariableExpression;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BinaryExpression::class)]
#[UsesClass(BinaryOperator::class)]
#[UsesClass(VariableExpression::class)]
#[Small]
final class BinaryExpressionTest extends TestCase
{
    public function testABinaryExpressionCarriesWhatIsAppliedAndWhatItIsAppliedTo(): void
    {
        $left = new VariableExpression('a');
        $right = new VariableExpression('b');
        $written = new BinaryExpression(BinaryOperator::And, $left, $right);

        self::assertSame(BinaryOperator::And, $written->operator);
        self::assertSame($left, $written->left);
        self::assertSame($right, $written->right);
    }
}
