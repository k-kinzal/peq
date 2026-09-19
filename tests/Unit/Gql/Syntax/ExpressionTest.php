<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax;

use App\Gql\Datum\DatumKind;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Syntax\Expression;
use App\Gql\Syntax\Expression\BinaryExpression;
use App\Gql\Syntax\Expression\BinaryOperator;
use App\Gql\Syntax\Expression\CallExpression;
use App\Gql\Syntax\Expression\CaseBranch;
use App\Gql\Syntax\Expression\CaseExpression;
use App\Gql\Syntax\Expression\IndexExpression;
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
#[CoversClass(Expression::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(BinaryExpression::class)]
#[UsesClass(BinaryOperator::class)]
#[UsesClass(CallExpression::class)]
#[UsesClass(CaseBranch::class)]
#[UsesClass(CaseExpression::class)]
#[UsesClass(IndexExpression::class)]
#[UsesClass(ListExpression::class)]
#[UsesClass(LiteralExpression::class)]
#[UsesClass(PropertyExpression::class)]
#[UsesClass(UnaryExpression::class)]
#[UsesClass(UnaryOperator::class)]
#[UsesClass(VariableExpression::class)]
#[Small]
final class ExpressionTest extends TestCase
{
    #[DataProvider('providerEveryKindOfExpression')]
    public function testEverythingAQueryComputesAValueFromIsAnExpressionOfItsOwnKind(Expression $expression, string $expected): void
    {
        self::assertSame($expected, $expression::class);
    }

    /**
     * @return iterable<string, array{Expression, class-string}>
     */
    public static function providerEveryKindOfExpression(): iterable
    {
        $value = new VariableExpression('p');
        $zero = new LiteralExpression(new IntegerDatum(0));

        yield 'a value written into the query' => [$zero, LiteralExpression::class];

        yield 'a name' => [$value, VariableExpression::class];

        yield 'a property' => [new PropertyExpression($value, 'name'), PropertyExpression::class];

        yield 'an index' => [new IndexExpression($value, $zero), IndexExpression::class];

        yield 'an operator over one value' => [new UnaryExpression(UnaryOperator::Not, $value), UnaryExpression::class];

        yield 'an operator over two' => [new BinaryExpression(BinaryOperator::And, $value, $value), BinaryExpression::class];

        yield 'a function call' => [new CallExpression('upper', [$value]), CallExpression::class];

        yield 'a list' => [new ListExpression([$value]), ListExpression::class];

        yield 'a choice' => [new CaseExpression(null, [new CaseBranch($value, $value)]), CaseExpression::class];
    }
}
