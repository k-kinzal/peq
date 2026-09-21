<?php

declare(strict_types=1);

namespace App\Gql\Parsing;

use App\Gql\GqlException;
use App\Gql\Syntax\Expression;
use App\Gql\Syntax\Expression\BinaryExpression;
use App\Gql\Syntax\Expression\BinaryOperator;
use App\Gql\Syntax\Expression\UnaryExpression;
use App\Gql\Syntax\Expression\UnaryOperator;

/**
 * Reads an expression, one layer of binding at a time.
 *
 * The layers are written out as methods rather than driven by a table of precedences,
 * because the order operators bind in is the part of a language a reader most needs
 * to be able to check, and a method per layer can be read against the specification
 * line by line. From loosest to tightest: `OR` and `XOR` together, `AND`, `NOT`, a
 * truth-value test, comparison, addition, multiplication, sign, and the things that
 * attach to a value — a property and a null test. Each is named after the production of
 * ISO/IEC 39075 it reads.
 *
 * Where an operand of a layer is read, the next tighter layer is called; where a
 * whole expression is read again — inside parentheses, inside a list, inside a
 * branch of a CASE — the loosest layer is called. That is what makes parentheses
 * mean what they always mean.
 *
 * @visibility App\Gql
 */
final readonly class ExpressionParser
{
    /**
     * Reads operands, which are whole expressions in every way but their binding.
     */
    private OperandParser $operands;

    /**
     * @param TokenReader $tokens The pieces of the query being read
     */
    public function __construct(
        private TokenReader $tokens,
    ) {
        $this->operands = new OperandParser($tokens, $this);
    }

    /**
     * Reads a whole expression.
     *
     * @example An expression reads as the tree its operators describe
     *     $parsed = (new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('a OR b AND c')))->parse();
     *     $parsed instanceof \App\Gql\Syntax\Expression\BinaryExpression ? $parsed->operator : null // => \App\Gql\Syntax\Expression\BinaryOperator::Or
     *
     * @return Expression The expression
     *
     * @throws GqlException If what is written is not an expression
     */
    public function parse(): Expression
    {
        return $this->parseOr();
    }

    /**
     * Reads a disjunction, the loosest-binding operators GQL has.
     *
     * `OR` and `XOR` are one layer, read left to right: ISO/IEC 39075 writes
     * `<boolean value expression>` as a boolean value expression followed by either of
     * them and a `<boolean term>`. So `a OR b XOR c` is `(a OR b) XOR c`, which is not
     * what a reader who expects `XOR` to bind tighter would guess, and gives a different
     * answer when `a`, `b` and `c` are all true.
     *
     * @example Disjunction binds loosest, so it is the top of the tree
     *     $parsed = (new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('a AND b OR c')))->parseOr();
     *     $parsed instanceof \App\Gql\Syntax\Expression\BinaryExpression ? $parsed->operator : null // => \App\Gql\Syntax\Expression\BinaryOperator::Or
     * @example Exclusive disjunction shares its layer, so the later one is the top
     *     $parsed = (new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('a OR b XOR c')))->parseOr();
     *     $parsed instanceof \App\Gql\Syntax\Expression\BinaryExpression ? $parsed->operator : null // => \App\Gql\Syntax\Expression\BinaryOperator::Xor
     *
     * @return Expression The expression
     *
     * @throws GqlException If what is written is not an expression
     */
    public function parseOr(): Expression
    {
        $left = $this->parseAnd();
        while (true) {
            if ($this->tokens->acceptKeyword('OR')) {
                $left = new BinaryExpression(BinaryOperator::Or, $left, $this->parseAnd());

                continue;
            }
            if (!$this->tokens->acceptKeyword('XOR')) {
                return $left;
            }
            $left = new BinaryExpression(BinaryOperator::Xor, $left, $this->parseAnd());
        }
    }

    /**
     * Reads a conjunction, GQL's `<boolean term>`.
     *
     * @example A conjunction binds tighter than either disjunction
     *     $parsed = (new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('a AND b')))->parseAnd();
     *     $parsed instanceof \App\Gql\Syntax\Expression\BinaryExpression ? $parsed->operator : null // => \App\Gql\Syntax\Expression\BinaryOperator::And
     *
     * @return Expression The expression
     *
     * @throws GqlException If what is written is not an expression
     */
    public function parseAnd(): Expression
    {
        $left = $this->parseNot();
        while ($this->tokens->acceptKeyword('AND')) {
            $left = new BinaryExpression(BinaryOperator::And, $left, $this->parseNot());
        }

        return $left;
    }

    /**
     * Reads a negation, GQL's `<boolean factor>`: at most one `NOT` before a test.
     *
     * `NOT a = b` is `NOT (a = b)`, while `NOT a AND b` is `(NOT a) AND b`. And `NOT NOT
     * a` is not an expression at all: the grammar allows one `NOT`, and a second is
     * written with parentheses.
     *
     * @example A negation takes the whole comparison after it
     *     $parsed = (new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('NOT a = b')))->parseNot();
     *     $parsed instanceof \App\Gql\Syntax\Expression\UnaryExpression ? $parsed->operator : null // => \App\Gql\Syntax\Expression\UnaryOperator::Not
     * @example A second one is not GQL
     *     (new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('NOT NOT a')))->parseNot() // throws \App\Gql\GqlException: syntax error
     *
     * @return Expression The expression
     *
     * @throws GqlException If what is written is not an expression
     */
    public function parseNot(): Expression
    {
        if (!$this->tokens->acceptKeyword('NOT')) {
            return $this->parseTest();
        }
        if ($this->tokens->atKeyword('NOT')) {
            $this->tokens->fail('an expression, since GQL writes one NOT before a test and a second one inside parentheses');
        }

        return new UnaryExpression(UnaryOperator::Not, $this->parseTest());
    }

    /**
     * Reads a truth-value test, GQL's `<boolean test>`, if one is written.
     *
     * `IS TRUE`, `IS FALSE` and `IS UNKNOWN` are how a query asks which of the three
     * truth values something has, and unlike every other comparison the answer is never
     * unknown: `NULL IS UNKNOWN` is true.
     *
     * @example A test is written after what it tests
     *     $parsed = (new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('a = b IS NOT TRUE')))->parseTest();
     *     $parsed instanceof \App\Gql\Syntax\Expression\UnaryExpression ? $parsed->operator : null // => \App\Gql\Syntax\Expression\UnaryOperator::IsNotTrue
     *
     * @return Expression The expression
     *
     * @throws GqlException If what is written is not an expression
     */
    public function parseTest(): Expression
    {
        $tested = $this->parseComparison();
        if (!$this->tokens->acceptKeyword('IS')) {
            return $tested;
        }
        $negated = $this->tokens->acceptKeyword('NOT');
        $operator = match (true) {
            $this->tokens->acceptKeyword('TRUE') => $negated ? UnaryOperator::IsNotTrue : UnaryOperator::IsTrue,
            $this->tokens->acceptKeyword('FALSE') => $negated ? UnaryOperator::IsNotFalse : UnaryOperator::IsFalse,
            $this->tokens->acceptKeyword('UNKNOWN') => $negated ? UnaryOperator::IsNotUnknown : UnaryOperator::IsUnknown,
            default => $this->tokens->fail('TRUE, FALSE or UNKNOWN'),
        };

        return new UnaryExpression($operator, $tested);
    }

    /**
     * Reads a comparison, if one is written.
     *
     * A comparison compares two values and is not itself one of them, so GQL writes at
     * most one: `a < b < c` is not an expression, and `(a < b) = c` says what it means.
     *
     * @example A comparison binds tighter than a negation
     *     $parsed = (new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('a < b')))->parseComparison();
     *     $parsed instanceof \App\Gql\Syntax\Expression\BinaryExpression ? $parsed->operator : null // => \App\Gql\Syntax\Expression\BinaryOperator::Less
     *
     * @return Expression The expression
     *
     * @throws GqlException If what is written is not an expression
     */
    public function parseComparison(): Expression
    {
        $left = $this->parseAdditive();
        $operator = self::comparisonIn($this->tokens);
        if ($operator === null) {
            return $left;
        }

        return new BinaryExpression($operator, $left, $this->parseAdditive());
    }

    /**
     * Reads the comparing operator standing at a reader, if there is one, and takes it.
     *
     * These six are GQL's `<comp op>`, and they are all of it. A membership test like
     * `x IN [1, 2]` reads well and is in other graph languages, but ISO/IEC 39075 writes
     * `IN` only after `FOR` and inside `LET ... IN ... END`, so it is not read here.
     *
     * @param TokenReader $tokens The pieces of the query being read
     *
     * @example A comparing operator is recognised and taken
     *     \App\Gql\Parsing\ExpressionParser::comparisonIn(\App\Gql\Parsing\TokenReader::of('>= 3')) // => \App\Gql\Syntax\Expression\BinaryOperator::GreaterOrEqual
     * @example A membership test is not one of them
     *     \App\Gql\Parsing\ExpressionParser::comparisonIn(\App\Gql\Parsing\TokenReader::of('IN [1]')) // => null
     *
     * @return null|BinaryOperator The operator, or null when none stands there
     */
    public static function comparisonIn(TokenReader $tokens): ?BinaryOperator
    {
        $token = $tokens->current();
        $operator = match (true) {
            $token->isSymbol('=') => BinaryOperator::Equal,
            $token->isSymbol('<>') => BinaryOperator::NotEqual,
            $token->isSymbol('<=') => BinaryOperator::LessOrEqual,
            $token->isSymbol('>=') => BinaryOperator::GreaterOrEqual,
            $token->isSymbol('<') => BinaryOperator::Less,
            $token->isSymbol('>') => BinaryOperator::Greater,
            default => null,
        };
        if ($operator !== null) {
            $tokens->take();
        }

        return $operator;
    }

    /**
     * Reads an addition, a subtraction or a concatenation.
     *
     * Concatenation binds with addition, as it does in SQL, which is why
     * `a || ' ' || b = c` compares the whole joined string rather than only `b`.
     *
     * @example Concatenation binds as tightly as addition does
     *     $parsed = (new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of("a || ' ' || b")))->parseAdditive();
     *     $parsed instanceof \App\Gql\Syntax\Expression\BinaryExpression ? $parsed->operator : null // => \App\Gql\Syntax\Expression\BinaryOperator::Concatenate
     *
     * @return Expression The expression
     *
     * @throws GqlException If what is written is not an expression
     */
    public function parseAdditive(): Expression
    {
        $left = $this->parseMultiplicative();
        while (true) {
            $operator = match (true) {
                $this->tokens->atSymbol('+') => BinaryOperator::Add,
                $this->tokens->atSymbol('-') => BinaryOperator::Subtract,
                $this->tokens->atSymbol('||') => BinaryOperator::Concatenate,
                default => null,
            };
            if ($operator === null) {
                return $left;
            }
            $this->tokens->take();
            $left = new BinaryExpression($operator, $left, $this->parseMultiplicative());
        }
    }

    /**
     * Reads a multiplication or a division.
     *
     * @example Multiplication binds tighter than addition
     *     $parsed = (new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('a * b')))->parseMultiplicative();
     *     $parsed instanceof \App\Gql\Syntax\Expression\BinaryExpression ? $parsed->operator : null // => \App\Gql\Syntax\Expression\BinaryOperator::Multiply
     *
     * @return Expression The expression
     *
     * @throws GqlException If what is written is not an expression
     */
    public function parseMultiplicative(): Expression
    {
        $left = $this->parseUnary();
        while (true) {
            $operator = match (true) {
                $this->tokens->atSymbol('*') => BinaryOperator::Multiply,
                $this->tokens->atSymbol('/') => BinaryOperator::Divide,
                default => null,
            };
            if ($operator === null) {
                return $left;
            }
            $this->tokens->take();
            $left = new BinaryExpression($operator, $left, $this->parseUnary());
        }
    }

    /**
     * Reads a sign written before a value.
     *
     * @example A sign takes only the value after it
     *     $parsed = (new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('-a')))->parseUnary();
     *     $parsed instanceof \App\Gql\Syntax\Expression\UnaryExpression ? $parsed->operator : null // => \App\Gql\Syntax\Expression\UnaryOperator::Negate
     *
     * @return Expression The expression
     *
     * @throws GqlException If what is written is not an expression
     */
    public function parseUnary(): Expression
    {
        if ($this->tokens->acceptSymbol('-')) {
            return new UnaryExpression(UnaryOperator::Negate, $this->parseUnary());
        }
        if ($this->tokens->acceptSymbol('+')) {
            return new UnaryExpression(UnaryOperator::Identity, $this->parseUnary());
        }

        return $this->operands->parse();
    }
}
