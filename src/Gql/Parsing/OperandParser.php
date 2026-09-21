<?php

declare(strict_types=1);

namespace App\Gql\Parsing;

use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\DecimalDatum;
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
 * parenthesised expression, a list, a conditional — and the two things that attach
 * themselves to a value once it has been read, which are a property and a null test.
 * Attaching them in a loop rather than by recursion is what makes `p.name IS NOT NULL`
 * read left to right the way it is written.
 *
 * @visibility App\Gql\Parsing
 */
final readonly class OperandParser
{
    /**
     * The functions GQL writes as aggregates, and so the only ones a set quantifier may be written in.
     *
     * These are the `<general set function type>` and `<binary set function type>` of
     * ISO/IEC 39075: `<general set function> ::= <general set function type> <left
     * paren> [ <set quantifier> ] <value expression> <right paren>`.
     */
    public const array SET_FUNCTIONS = ['AVG', 'COUNT', 'MAX', 'MIN', 'SUM', 'COLLECT_LIST', 'STDDEV_SAMP', 'STDDEV_POP', 'PERCENTILE_CONT', 'PERCENTILE_DISC'];

    /**
     * @param TokenReader      $tokens      The pieces of the query being read
     * @param ExpressionParser $expressions Where a whole expression is read again, inside brackets
     */
    public function __construct(
        private TokenReader $tokens,
        private ExpressionParser $expressions,
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
     * Two things do: a property, GQL's `<property reference>`, and a null test, its
     * `<null predicate>`, whose operand is a `<value expression primary>`. A subscript
     * like `xs[0]` does not: ISO/IEC 39075 writes a left bracket in a value only to
     * construct a list, so it is not read here. `IS` followed by a truth value is not
     * taken either — that is a test of a whole predicate, read further out.
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
                $subject = new PropertyExpression($subject, NameReader::identifier($this->tokens));

                continue;
            }
            if (!$this->atNullTest()) {
                return $subject;
            }
            $this->tokens->expectKeyword('IS');
            $negated = $this->tokens->acceptKeyword('NOT');
            $this->tokens->expectKeyword('NULL');
            $subject = new UnaryExpression($negated ? UnaryOperator::IsNotNull : UnaryOperator::IsNull, $subject);
        }
    }

    /**
     * Reports whether a null test, rather than a truth-value test, stands here.
     *
     * @example A null test is `IS NULL` or `IS NOT NULL`
     *     $reader = \App\Gql\Parsing\TokenReader::of('IS NOT NULL');
     *     (new \App\Gql\Parsing\OperandParser($reader, new \App\Gql\Parsing\ExpressionParser($reader)))->atNullTest() // => true
     * @example `IS TRUE` is not one
     *     $reader = \App\Gql\Parsing\TokenReader::of('IS TRUE');
     *     (new \App\Gql\Parsing\OperandParser($reader, new \App\Gql\Parsing\ExpressionParser($reader)))->atNullTest() // => false
     *
     * @return bool True when it does
     */
    public function atNullTest(): bool
    {
        if (!$this->tokens->atKeyword('IS')) {
            return false;
        }
        $next = $this->tokens->peek();

        return $next->isKeyword('NULL') || ($next->isKeyword('NOT') && $this->tokens->peek(2)->isKeyword('NULL'));
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
        if (NameReader::atWord($this->tokens) && $this->tokens->peek()->isSymbol('(')) {
            return $this->parseCall($this->tokens->expectName());
        }

        return new VariableExpression(NameReader::variable($this->tokens));
    }

    /**
     * Reads a value written directly into the query, if one stands here.
     *
     * @example A number written into a query is read as a number
     *     $parsed = (new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('42')))->parse();
     *     $parsed instanceof \App\Gql\Syntax\Expression\LiteralExpression ? $parsed->value->toText() : null // => '42'
     * @example A number with a point is exact
     *     $parsed = (new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('1.5')))->parse();
     *     $parsed instanceof \App\Gql\Syntax\Expression\LiteralExpression ? $parsed->value->kind() : null // => \App\Gql\Datum\DatumKind::Decimal
     * @example The unknown truth value is written as the absence of one
     *     $parsed = (new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('UNKNOWN')))->parse();
     *     $parsed instanceof \App\Gql\Syntax\Expression\LiteralExpression ? $parsed->value->kind() : null // => \App\Gql\Datum\DatumKind::Null
     *
     * @return null|LiteralExpression The literal, or null when what stands here is not one
     *
     * @throws GqlException If a number has more digits than an exact number can hold
     */
    public function parseLiteral(): ?LiteralExpression
    {
        $token = $this->tokens->current();
        $value = match (true) {
            $token->kind === TokenKind::Integer => IntegerDatum::of(DecimalDatum::whole($token->value)),
            $token->kind === TokenKind::Decimal => DecimalDatum::written($token->value),
            $token->kind === TokenKind::Approximate => self::approximate($token->value),
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
     * GQL writes an asterisk in one call only, `COUNT(*)`, and a set quantifier —
     * `DISTINCT` or `ALL` — only in an aggregate; anywhere else they are refused.
     *
     * @param string $name The function name, already read
     *
     * @example A call over rows rather than over a value says so
     *     $parsed = (new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('count(*)')))->parse();
     *     $parsed instanceof \App\Gql\Syntax\Expression\CallExpression ? $parsed->star : null // => true
     * @example A call that drops repeated values says so too
     *     $parsed = (new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('count(DISTINCT p)')))->parse();
     *     $parsed instanceof \App\Gql\Syntax\Expression\CallExpression ? $parsed->distinct : null // => true
     * @example An asterisk is COUNT's alone
     *     (new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('sum(*)')))->parse() // throws \App\Gql\GqlException: COUNT(*)
     * @example And a set quantifier an aggregate's alone
     *     (new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('upper(DISTINCT p)')))->parse() // throws \App\Gql\GqlException: aggregate
     *
     * @return CallExpression The call
     *
     * @throws GqlException If the arguments are not finished, or are written in a way GQL writes only for another function
     */
    public function parseCall(string $name): CallExpression
    {
        $this->tokens->expectSymbol('(');
        $aggregate = in_array(strtoupper($name), self::SET_FUNCTIONS, true);
        $quantified = $this->tokens->atKeyword('DISTINCT') || $this->tokens->atKeyword('ALL');
        if ($quantified && !$aggregate) {
            $this->tokens->fail('an argument, since only an aggregate function is written with DISTINCT or ALL');
        }
        $distinct = $this->tokens->acceptKeyword('DISTINCT');
        if (!$distinct) {
            $this->tokens->acceptKeyword('ALL');
        }
        if ($this->tokens->atSymbol('*')) {
            if (strtoupper($name) !== 'COUNT' || $quantified) {
                $this->tokens->fail('an argument, since GQL writes an asterisk only as COUNT(*)');
            }
            $this->tokens->take();
            $this->tokens->expectSymbol(')');

            return new CallExpression($name, [], false, true);
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
        $subject = $this->tokens->atKeyword('WHEN') || $this->tokens->atKeyword('END')
            ? null
            : $this->expressions->parse();

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
