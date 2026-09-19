<?php

declare(strict_types=1);

namespace App\Gql\Parsing;

use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\FloatDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\GqlException;
use App\Gql\Lexing\TokenKind;
use App\Gql\Syntax\Expression;
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

/**
 * Reads the things operators are written between.
 *
 * Everything here binds tighter than every operator: a literal, a name, a call, a
 * parenthesised expression, a list, a conditional — and the three things that attach
 * themselves to a value once it has been read, which are a property, an index and a
 * null test. Attaching them in a loop rather than by recursion is what makes
 * `e[0].line IS NOT NULL` read left to right the way it is written.
 *
 * @visibility App\Gql\Parsing
 */
final class OperandParser
{
    /**
     * @param TokenReader      $tokens      The pieces of the query being read
     * @param ExpressionParser $expressions Where a whole expression is read again, inside brackets
     */
    public function __construct(
        private readonly TokenReader $tokens,
        private readonly ExpressionParser $expressions,
    ) {}

    /**
     * Reads an operand together with whatever attaches to it.
     *
     * @example A property attaches to the value it is read off
     *     $parsed = (new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('p.firstName')))->parse();
     *     $parsed instanceof \App\Gql\Syntax\Expression\PropertyExpression ? $parsed->property : null // => 'firstName'
     *
     * @return Expression The expression
     *
     * @throws GqlException If what is written is not an operand
     */
    public function parse(): Expression
    {
        return $this->parseAttached($this->parsePrimary());
    }

    /**
     * Reads whatever attaches to a value that has already been read.
     *
     * @param Expression $subject The value read so far
     *
     * @example A null test attaches to the whole value before it
     *     $parser = new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('p.name IS NOT NULL'));
     *     $parsed = $parser->parse();
     *     $parsed instanceof \App\Gql\Syntax\Expression\UnaryExpression ? $parsed->operator : null // => \App\Gql\Syntax\Expression\UnaryOperator::IsNotNull
     *
     * @return Expression The expression, with everything that attached to it
     *
     * @throws GqlException If something is attached that is not finished
     */
    public function parseAttached(Expression $subject): Expression
    {
        while (true) {
            if ($this->tokens->acceptSymbol('.')) {
                $subject = new PropertyExpression($subject, $this->tokens->expectName());

                continue;
            }
            if ($this->tokens->acceptSymbol('[')) {
                $subject = new IndexExpression($subject, $this->expressions->parse());
                $this->tokens->expectSymbol(']');

                continue;
            }
            if (!$this->tokens->acceptKeyword('IS')) {
                return $subject;
            }
            $negated = $this->tokens->acceptKeyword('NOT');
            $this->tokens->expectKeyword('NULL');
            $subject = new UnaryExpression($negated ? UnaryOperator::IsNotNull : UnaryOperator::IsNull, $subject);
        }
    }

    /**
     * Reads a value written on its own.
     *
     * @example A name that is not called is a variable
     *     $parsed = (new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('p')))->parse();
     *     $parsed instanceof \App\Gql\Syntax\Expression\VariableExpression ? $parsed->name : null // => 'p'
     * @example A name that is called is a call
     *     $parsed = (new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('upper(p)')))->parse();
     *     $parsed instanceof \App\Gql\Syntax\Expression\CallExpression ? $parsed->name : null // => 'upper'
     *
     * @return Expression The expression
     *
     * @throws GqlException If what is written is not a value
     */
    public function parsePrimary(): Expression
    {
        $literal = $this->parseLiteral();
        if ($literal !== null) {
            return $literal;
        }
        if ($this->tokens->acceptSymbol('(')) {
            $inner = $this->expressions->parse();
            $this->tokens->expectSymbol(')');

            return $inner;
        }
        if ($this->tokens->atSymbol('[')) {
            return $this->parseList();
        }
        if ($this->tokens->atKeyword('CASE')) {
            return $this->parseCase();
        }
        if (!$this->tokens->atName()) {
            $this->tokens->fail('an expression');
        }

        $name = $this->tokens->expectName();

        return $this->tokens->atSymbol('(') ? $this->parseCall($name) : new VariableExpression($name);
    }

    /**
     * Reads a value written directly into the query, if one stands here.
     *
     * @example A number written into a query is read as a number
     *     $parsed = (new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('42')))->parse();
     *     $parsed instanceof \App\Gql\Syntax\Expression\LiteralExpression ? $parsed->value->toText() : null // => '42'
     * @example The unknown truth value is written as the absence of one
     *     $parsed = (new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('UNKNOWN')))->parse();
     *     $parsed instanceof \App\Gql\Syntax\Expression\LiteralExpression ? $parsed->value->kind() : null // => \App\Gql\Datum\DatumKind::Null
     *
     * @return null|LiteralExpression The literal, or null when what stands here is not one
     */
    public function parseLiteral(): ?LiteralExpression
    {
        $token = $this->tokens->current();
        $value = match (true) {
            $token->kind === TokenKind::Integer => IntegerDatum::of((int) $token->value),
            $token->kind === TokenKind::Decimal => self::approximate($token->value),
            $token->kind === TokenKind::Text => StringDatum::of($token->value),
            $token->isKeyword('TRUE') => BooleanDatum::of(true),
            $token->isKeyword('FALSE') => BooleanDatum::of(false),
            $token->isKeyword('NULL'), $token->isKeyword('UNKNOWN') => NullDatum::unknown(),
            default => null,
        };
        if ($value === null) {
            return null;
        }
        $this->tokens->take();

        return new LiteralExpression($value);
    }

    /**
     * Reads an approximate number written with or without the suffix that marks it one.
     *
     * @param string $written The number as the query wrote it
     *
     * @example A suffix marks a number approximate without changing what it is worth
     *     \App\Gql\Parsing\OperandParser::approximate('1.0d')->value // => 1.0
     *
     * @return FloatDatum The number
     */
    public static function approximate(string $written): FloatDatum
    {
        return FloatDatum::of((float) rtrim($written, 'dDfF'));
    }

    /**
     * Reads a list written out in the query.
     *
     * @example A list is read as the values it holds
     *     $parsed = (new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of("['a', 'b']")))->parse();
     *     $parsed instanceof \App\Gql\Syntax\Expression\ListExpression ? count($parsed->items) : null // => 2
     * @example A list can hold nothing
     *     $parsed = (new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('[]')))->parse();
     *     $parsed instanceof \App\Gql\Syntax\Expression\ListExpression ? $parsed->items : null // => []
     *
     * @return ListExpression The list
     *
     * @throws GqlException If the list is not finished
     */
    public function parseList(): ListExpression
    {
        $this->tokens->expectSymbol('[');
        if ($this->tokens->acceptSymbol(']')) {
            return new ListExpression();
        }

        $items = [];
        do {
            $items[] = $this->expressions->parse();
        } while ($this->tokens->acceptSymbol(','));
        $this->tokens->expectSymbol(']');

        return new ListExpression($items);
    }

    /**
     * Reads the arguments a function is applied to.
     *
     * @param string $name The function name, already read
     *
     * @example A call over rows rather than over a value says so
     *     $parsed = (new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('count(*)')))->parse();
     *     $parsed instanceof \App\Gql\Syntax\Expression\CallExpression ? $parsed->star : null // => true
     * @example A call that drops repeated values says so too
     *     $parsed = (new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('count(DISTINCT p)')))->parse();
     *     $parsed instanceof \App\Gql\Syntax\Expression\CallExpression ? $parsed->distinct : null // => true
     *
     * @return CallExpression The call
     *
     * @throws GqlException If the arguments are not finished
     */
    public function parseCall(string $name): CallExpression
    {
        $this->tokens->expectSymbol('(');
        $distinct = $this->tokens->acceptKeyword('DISTINCT');
        if ($this->tokens->acceptSymbol('*')) {
            $this->tokens->expectSymbol(')');

            return new CallExpression($name, [], $distinct, true);
        }
        if ($this->tokens->acceptSymbol(')')) {
            return new CallExpression($name, [], $distinct);
        }

        $arguments = [];
        do {
            $arguments[] = $this->expressions->parse();
        } while ($this->tokens->acceptSymbol(','));
        $this->tokens->expectSymbol(')');

        return new CallExpression($name, $arguments, $distinct);
    }

    /**
     * Reads a choice between values.
     *
     * A subject written before the first branch makes it the simple form, where each
     * branch is a value the subject is compared against; written without one, each
     * branch is its own question.
     *
     * @example A choice tried branch by branch has no subject
     *     $parser = new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of("CASE WHEN a THEN 'x' ELSE 'y' END"));
     *     $parsed = $parser->parse();
     *     $parsed instanceof \App\Gql\Syntax\Expression\CaseExpression ? $parsed->subject : null // => null
     * @example A choice against one value has it as its subject
     *     $parser = new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of("CASE a WHEN 1 THEN 'x' END"));
     *     $parsed = $parser->parse();
     *     $parsed instanceof \App\Gql\Syntax\Expression\CaseExpression ? $parsed->subject !== null : null // => true
     *
     * @return CaseExpression The choice
     *
     * @throws GqlException If the choice is not finished, or offers no branch
     */
    public function parseCase(): CaseExpression
    {
        $this->tokens->expectKeyword('CASE');
        $subject = $this->tokens->atKeyword('WHEN') ? null : $this->expressions->parse();

        $branches = [];
        while ($this->tokens->acceptKeyword('WHEN')) {
            $when = $this->expressions->parse();
            $this->tokens->expectKeyword('THEN');
            $branches[] = new CaseBranch($when, $this->expressions->parse());
        }
        if ($branches === []) {
            $this->tokens->fail('WHEN');
        }

        $otherwise = $this->tokens->acceptKeyword('ELSE') ? $this->expressions->parse() : null;
        $this->tokens->expectKeyword('END');

        return new CaseExpression($subject, $branches, $otherwise);
    }
}
