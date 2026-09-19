<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Evaluation;

use App\Gql\Argument\NumberArgument;
use App\Gql\Argument\TextArgument;
use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumIdentity;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DatumOrder;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\FloatDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\Evaluation\Arithmetic;
use App\Gql\Evaluation\BinaryOperation;
use App\Gql\Evaluation\Comparison;
use App\Gql\Evaluation\Logic;
use App\Gql\Evaluation\Membership;
use App\Gql\Evaluation\TextOperation;
use App\Gql\GqlException;
use App\Gql\Lexing\Lexer;
use App\Gql\Lexing\QuotedScanner;
use App\Gql\Lexing\SourceCursor;
use App\Gql\Lexing\Token;
use App\Gql\Lexing\TokenKind;
use App\Gql\Lexing\TokenList;
use App\Gql\Parsing\ExpressionParser;
use App\Gql\Parsing\OperandParser;
use App\Gql\Parsing\TokenReader;
use App\Gql\StatusCode;
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
#[CoversClass(BinaryOperation::class)]
#[UsesClass(BooleanDatum::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DatumIdentity::class)]
#[UsesClass(DatumOrder::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(FloatDatum::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(Lexer::class)]
#[UsesClass(QuotedScanner::class)]
#[UsesClass(SourceCursor::class)]
#[UsesClass(Token::class)]
#[UsesClass(TokenKind::class)]
#[UsesClass(TokenList::class)]
#[UsesClass(ExpressionParser::class)]
#[UsesClass(OperandParser::class)]
#[UsesClass(TokenReader::class)]
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
#[UsesClass(NumberArgument::class)]
#[UsesClass(TextArgument::class)]
#[UsesClass(Arithmetic::class)]
#[UsesClass(Comparison::class)]
#[UsesClass(Logic::class)]
#[UsesClass(Membership::class)]
#[UsesClass(TextOperation::class)]
#[UsesClass(Datum::class)]
#[Small]
final class BinaryOperationTest extends TestCase
{
    /**
     * @throws GqlException
     */
    #[DataProvider('providerOperatorsAndTheRuleTheyFollow')]
    public function testApplySortsEveryOperatorIntoTheRulesItFollows(
        BinaryOperator $operator,
        Datum $left,
        Datum $right,
        string $expected,
    ): void {
        self::assertSame($expected, BinaryOperation::apply($operator, $left, $right)->toText());
    }

    /**
     * @return iterable<string, array{BinaryOperator, Datum, Datum, string}>
     */
    public static function providerOperatorsAndTheRuleTheyFollow(): iterable
    {
        $one = new IntegerDatum(1);
        $two = new IntegerDatum(2);
        $yes = new BooleanDatum(true);
        $no = new BooleanDatum(false);
        $name = new StringDatum('UserController');
        $suffix = new StringDatum('Controller');

        yield 'addition follows the arithmetic rules' => [BinaryOperator::Add, $one, $two, '3'];

        yield 'subtraction follows them too' => [BinaryOperator::Subtract, $one, $two, '-1'];

        yield 'multiplication follows them too' => [BinaryOperator::Multiply, $one, $two, '2'];

        yield 'division follows them too' => [BinaryOperator::Divide, $one, $two, '0'];

        yield 'joining follows the rules about text' => [BinaryOperator::Concatenate, $one, $two, '12'];

        yield 'containment follows them too' => [BinaryOperator::Contains, $name, $suffix, 'TRUE'];

        yield 'a prefix follows them too' => [BinaryOperator::StartsWith, $name, $suffix, 'FALSE'];

        yield 'a suffix follows them too' => [BinaryOperator::EndsWith, $name, $suffix, 'TRUE'];

        yield 'equality follows the comparing rules' => [BinaryOperator::Equal, $one, $two, 'FALSE'];

        yield 'inequality follows them too' => [BinaryOperator::NotEqual, $one, $two, 'TRUE'];

        yield 'ordering follows them too' => [BinaryOperator::Less, $one, $two, 'TRUE'];

        yield 'ordering the other way follows them too' => [BinaryOperator::Greater, $one, $two, 'FALSE'];

        yield 'ordering with equality follows them too' => [BinaryOperator::LessOrEqual, $one, $two, 'TRUE'];

        yield 'and the other way round' => [BinaryOperator::GreaterOrEqual, $one, $two, 'FALSE'];

        yield 'membership follows the rules about lists' => [BinaryOperator::In, $one, new ListDatum([$one]), 'TRUE'];

        yield 'and its refusal' => [BinaryOperator::NotIn, $one, new ListDatum([$one]), 'FALSE'];

        yield 'conjunction follows three-valued logic' => [BinaryOperator::And, $yes, $no, 'FALSE'];

        yield 'disjunction follows it too' => [BinaryOperator::Or, $yes, $no, 'TRUE'];

        yield 'exclusive disjunction follows it too' => [BinaryOperator::Xor, $yes, $no, 'TRUE'];
    }

    /**
     * @throws GqlException
     */
    public function testApplyLetsAConjunctionBeDecidedByOneFalseSide(): void
    {
        self::assertSame('FALSE', BinaryOperation::apply(BinaryOperator::And, new BooleanDatum(false), new NullDatum())->toText());
    }

    /**
     * @throws GqlException
     */
    public function testApplyRefusesMembershipUnderThreeValuedLogic(): void
    {
        $within = new ListDatum([new IntegerDatum(1), new NullDatum()]);

        self::assertSame(DatumKind::Null, BinaryOperation::apply(BinaryOperator::NotIn, new IntegerDatum(2), $within)->kind());
    }
}
