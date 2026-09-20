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
 * line by line. From loosest to tightest: `OR`, `XOR`, `AND`, `NOT`, comparison,
 * addition, multiplication, sign, and the things that attach to a value — a property,
 * an index, a null test.
 *
 * Where an operand of a layer is read, the next tighter layer is called; where a
 * whole expression is read again — inside parentheses, inside a list, inside a
 * branch of a CASE — the loosest layer is called. That is what makes parentheses
 * mean what they always mean.
 *
 * @visibility App\Gql
 */
final class ExpressionParser
{
    /**
     * Reads operands, which are whole expressions in every way but their binding.
     */
    private readonly OperandParser $operands;

    /**
     * @param TokenReader $tokens The pieces of the query being read
     */
    public function __construct(
        private readonly TokenReader $tokens,
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
     * Reads a disjunction, the loosest-binding operator GQL has.
     *
     * @example Disjunction binds loosest, so it is the top of the tree
     *     $parsed = (new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('a AND b OR c')))->parseOr();
     *     $parsed instanceof \App\Gql\Syntax\Expression\BinaryExpression ? $parsed->operator : null // => \App\Gql\Syntax\Expression\BinaryOperator::Or
     *
     * @return Expression The expression
     *
     * @throws GqlException If what is written is not an expression
     */
    public function parseOr(): Expression
    {
        $left = $this->parseXor();
        while ($this->tokens->acceptKeyword('OR')) {
            $left = new BinaryExpression(BinaryOperator::Or, $left, $this->parseXor());
        }

        return $left;
    }

    /**
     * Reads an exclusive disjunction.
     *
     * @example An exclusive disjunction binds tighter than a plain one
     *     $parsed = (new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('a XOR b')))->parseXor();
     *     $parsed instanceof \App\Gql\Syntax\Expression\BinaryExpression ? $parsed->operator : null // => \App\Gql\Syntax\Expression\BinaryOperator::Xor
     *
     * @return Expression The expression
     *
     * @throws GqlException If what is written is not an expression
     */
    public function parseXor(): Expression
    {
        $left = $this->parseAnd();
        while ($this->tokens->acceptKeyword('XOR')) {
            $left = new BinaryExpression(BinaryOperator::Xor, $left, $this->parseAnd());
        }

        return $left;
    }

    /**
     * Reads a conjunction.
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
     * Reads a negation, which binds tighter than the connectives and looser than a comparison.
     *
     * That placement is the one most often got wrong: `NOT a = b` is `NOT (a = b)`,
     * while `NOT a AND b` is `(NOT a) AND b`.
     *
     * @example A negation takes the whole comparison after it
     *     $parsed = (new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('NOT a = b')))->parseNot();
     *     $parsed instanceof \App\Gql\Syntax\Expression\UnaryExpression ? $parsed->operator : null // => \App\Gql\Syntax\Expression\UnaryOperator::Not
     *
     * @return Expression The expression
     *
     * @throws GqlException If what is written is not an expression
     */
    public function parseNot(): Expression
    {
        if ($this->tokens->acceptKeyword('NOT')) {
            return new UnaryExpression(UnaryOperator::Not, $this->parseNot());
        }

        return $this->parseComparison();
    }

    /**
     * Reads a comparison, a list membership test or a string predicate.
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
        while (($operator = self::comparisonIn($this->tokens)) !== null) {
            $left = new BinaryExpression($operator, $left, $this->parseAdditive());
        }

        return $left;
    }

    /**
     * Reads the comparing operator standing at a reader, if there is one, and takes it.
     *
     * `NOT IN` is read here rather than by the lexer because `NOT` is an operator of
     * its own everywhere else, and only the pair is a refused membership test.
     *
     * @param TokenReader $tokens The pieces of the query being read
     *
     * @example A comparing operator is recognised and taken
     *     \App\Gql\Parsing\ExpressionParser::comparisonIn(\App\Gql\Parsing\TokenReader::of('>= 3')) // => \App\Gql\Syntax\Expression\BinaryOperator::GreaterOrEqual
     * @example A refused membership test is one operator, not two
     *     \App\Gql\Parsing\ExpressionParser::comparisonIn(\App\Gql\Parsing\TokenReader::of('NOT IN [1]')) // => \App\Gql\Syntax\Expression\BinaryOperator::NotIn
     * @example Anything else leaves the reading where it was
     *     \App\Gql\Parsing\ExpressionParser::comparisonIn(\App\Gql\Parsing\TokenReader::of('AND b')) // => null
     *
     * @return null|BinaryOperator The operator, or null when none stands there
     */
    public static function comparisonIn(TokenReader $tokens): ?BinaryOperator
    {
        $token = $tokens->current();
        $single = match (true) {
            $token->isSymbol('=') => BinaryOperator::Equal,
            $token->isSymbol('<>') => BinaryOperator::NotEqual,
            $token->isSymbol('<=') => BinaryOperator::LessOrEqual,
            $token->isSymbol('>=') => BinaryOperator::GreaterOrEqual,
            $token->isSymbol('<') => BinaryOperator::Less,
            $token->isSymbol('>') => BinaryOperator::Greater,
            $token->isKeyword('IN') => BinaryOperator::In,
            default => null,
        };
        if ($single !== null) {
            $tokens->take();

            return $single;
        }

        return self::twoWordComparisonIn($tokens);
    }

    /**
     * Reads a comparing operator written as two words, if one stands at a reader.
     *
     * @param TokenReader $tokens The pieces of the query being read
     *
     * @example A two-word operator is taken as a whole
     *     \App\Gql\Parsing\ExpressionParser::twoWordComparisonIn(\App\Gql\Parsing\TokenReader::of('NOT IN [1]')) // => \App\Gql\Syntax\Expression\BinaryOperator::NotIn
     * @example A first word that is not followed by its second is left alone
     *     \App\Gql\Parsing\ExpressionParser::twoWordComparisonIn(\App\Gql\Parsing\TokenReader::of('NOT b')) // => null
     *
     * @return null|BinaryOperator The operator, or null when none stands there
     */
    public static function twoWordComparisonIn(TokenReader $tokens): ?BinaryOperator
    {
        $first = $tokens->current();
        $second = $tokens->peek();
        $operator = match (true) {
            $first->isKeyword('NOT') && $second->isKeyword('IN') => BinaryOperator::NotIn,
            default => null,
        };
        if ($operator === null) {
            return null;
        }
        $tokens->take();
        $tokens->take();

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
