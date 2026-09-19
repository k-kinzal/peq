<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Evaluation;

use App\Gql\Argument\TextArgument;
use App\Gql\Datum\BooleanDatum;
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
use App\Gql\Evaluation\Logic;
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
use Tests\Fixture\Gql\ExpressionWorth;

/**
 * @internal
 */
#[CoversClass(TextOperation::class)]
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
#[UsesClass(TextArgument::class)]
#[UsesClass(Logic::class)]
#[Small]
final class TextOperationTest extends TestCase
{
    /**
     * @throws GqlException
     */
    #[DataProvider('providerTextOperations')]
    public function testApplyAsksTheQuestionTheOperatorNames(string $written, string $expected): void
    {
        self::assertSame($expected, ExpressionWorth::of($written));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerTextOperations(): iterable
    {
        yield 'a namespace asked for as a prefix' => ["'App\\\\Domain\\\\Invoice' STARTS WITH 'App'", 'TRUE'];

        yield 'a prefix that is not there' => ["'App\\\\Domain' STARTS WITH 'Tests'", 'FALSE'];

        yield 'a convention asked for as a suffix' => ["'UserController' ENDS WITH 'Controller'", 'TRUE'];

        yield 'a word looked for anywhere in a path' => ["'src/Http/Kernel.php' CONTAINS 'Http'", 'TRUE'];

        yield 'anything asked of an absent value is undecided' => ["NULL CONTAINS 'x'", 'NULL'];

        yield 'anything asked against an absent value is undecided' => ["'x' CONTAINS NULL", 'NULL'];

        yield 'two strings joined' => ["'a' || 'b'", 'ab'];

        yield 'a string joined to a number' => ["'line ' || 12", 'line 12'];

        yield 'two lists joined' => ['[1] || [2]', '[1, 2]'];
    }

    /**
     * @throws GqlException
     */
    public function testApplyReportsAValueThatCannotBeReadAsAString(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('a string was expected');

        ExpressionWorth::of("[1] CONTAINS 'x'");
    }

    public function testConcatenateJoinsTwoStringsIntoALongerOne(): void
    {
        self::assertSame('ab', TextOperation::concatenate(new StringDatum('a'), new StringDatum('b'))->toText());
    }

    public function testConcatenateJoinsTwoListsIntoALongerOne(): void
    {
        $joined = TextOperation::concatenate(
            new ListDatum([new IntegerDatum(1)]),
            new ListDatum([new IntegerDatum(2)]),
        );

        self::assertSame('[1, 2]', $joined->toText());
    }

    public function testConcatenateJoinsAListToAStringAsText(): void
    {
        self::assertSame('[1]x', TextOperation::concatenate(new ListDatum([new IntegerDatum(1)]), new StringDatum('x'))->toText());
    }
}
