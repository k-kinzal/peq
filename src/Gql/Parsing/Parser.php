<?php

declare(strict_types=1);

namespace App\Gql\Parsing;

use App\Gql\GqlException;
use App\Gql\Lexing\TokenKind;
use App\Gql\Syntax\Clause;
use App\Gql\Syntax\Clause\FilterClause;
use App\Gql\Syntax\Clause\LetClause;
use App\Gql\Syntax\Clause\MatchClause;
use App\Gql\Syntax\Clause\PageClause;
use App\Gql\Syntax\Clause\ReturnClause;
use App\Gql\Syntax\Clause\VariableBinding;
use App\Gql\Syntax\Query;
use App\Gql\Syntax\QueryBlock;
use App\Gql\Syntax\SetOperator;

/**
 * Reads a GQL query.
 *
 * A query is a run of clauses, optionally combined with another run by a set
 * operator. Reading it is therefore mostly deciding which clause is being written,
 * which the first word of each one settles, and reading it — the work of which is
 * shared with the parsers for expressions, patterns and projections.
 *
 * What is not read here is as telling as what is. GQL's standard has statements for
 * changing a graph, for managing sessions and for declaring graph types; peq answers
 * questions about source code it has just analysed, and a query that could change the
 * graph would be describing a codebase that does not exist. Those statements are
 * therefore not accepted rather than silently ignored, and a query that uses one is
 * told so.
 *
 * @visibility App\Gql
 */
final readonly class Parser
{
    /**
     * The words that can begin a clause.
     */
    private const array CLAUSES = ['MATCH', 'OPTIONAL', 'LET', 'FILTER', 'ORDER', 'OFFSET', 'SKIP', 'LIMIT', 'RETURN'];

    /**
     * Reads expressions wherever a value is written.
     */
    private ExpressionParser $expressions;

    /**
     * Reads the drawing of a graph a MATCH is written as.
     */
    private PatternParser $patterns;

    /**
     * Reads what a query shows the reader.
     */
    private ResultParser $results;

    /**
     * @param TokenReader $tokens The pieces of the query being read
     */
    public function __construct(
        private TokenReader $tokens,
    ) {
        $this->expressions = new ExpressionParser($tokens);
        $this->patterns = new PatternParser($tokens, $this->expressions);
        $this->results = new ResultParser($tokens, $this->expressions);
    }

    /**
     * Reads a query written as text.
     *
     * @param string $source The query, as it was written
     *
     * @example A query reads as the clauses it is written from
     *     count(\App\Gql\Parsing\Parser::read('MATCH (p) RETURN p')->blocks[0]->clauses) // => 2
     * @example A query that is not GQL says what was expected and where
     *     \App\Gql\Parsing\Parser::read('MATCH (p) RETRUN p') // throws \App\Gql\GqlException: syntax error
     *
     * @return Query The query
     *
     * @throws GqlException If what is written is not a GQL query
     */
    public static function read(string $source): Query
    {
        return (new self(TokenReader::of($source)))->parse();
    }

    /**
     * Reads a whole query, set operators and all.
     *
     * Anything left over after the last block is reported rather than ignored: a
     * query with a stray word at the end is a query its author got wrong, and running
     * the part that parsed would answer a question nobody asked.
     *
     * @example A query can combine two runs of clauses
     *     $parsed = \App\Gql\Parsing\Parser::read('MATCH (p) RETURN p UNION ALL MATCH (q) RETURN q');
     *     $parsed->operators[0] // => \App\Gql\Syntax\SetOperator::UnionAll
     *
     * @return Query The query
     *
     * @throws GqlException If what is written is not a GQL query
     */
    public function parse(): Query
    {
        $blocks = [$this->parseBlock()];
        $operators = [];
        while (($operator = $this->parseSetOperator()) !== null) {
            $operators[] = $operator;
            $blocks[] = $this->parseBlock();
        }
        if ($this->tokens->current()->kind !== TokenKind::End) {
            StatementRefusal::reject($this->tokens);
            $this->tokens->fail('the end of the query');
        }

        return new Query($blocks, $operators);
    }

    /**
     * Reads one run of clauses, up to and including what it shows.
     *
     * GQL writes a linear query as an optional run of clauses followed by a result
     * statement, and the result statement is not optional: `MATCH (p)` on its own is
     * not a program, however clear what it was meant to ask. peq requires the RETURN
     * for that reason rather than answering the question anyway — the value of a query
     * language a reader already knows is spent the first time it accepts something the
     * language does not.
     *
     * @example A run of clauses goes on as long as clauses do
     *     $reader = \App\Gql\Parsing\TokenReader::of('MATCH (p) FILTER p.line > 1 RETURN p');
     *     count((new \App\Gql\Parsing\Parser($reader))->parseBlock()->clauses) // => 3
     * @example A run that never says what to show is not a query
     *     (new \App\Gql\Parsing\Parser(\App\Gql\Parsing\TokenReader::of('MATCH (p)')))->parseBlock() // throws \App\Gql\GqlException: RETURN
     *
     * @return QueryBlock The run
     *
     * @throws GqlException If what is written is not a run of clauses ending in one
     */
    public function parseBlock(): QueryBlock
    {
        $clauses = [$this->parseClause()];
        while ($this->atClause()) {
            $clauses[] = $this->parseClause();
        }
        if (!$clauses[count($clauses) - 1] instanceof ReturnClause) {
            StatementRefusal::reject($this->tokens);
            $this->tokens->fail('a RETURN statement, which is what GQL shows a query\'s answer with');
        }

        return new QueryBlock($clauses);
    }

    /**
     * Reports whether a clause begins here.
     *
     * @example A clause begins with the word that names it
     *     (new \App\Gql\Parsing\Parser(\App\Gql\Parsing\TokenReader::of('RETURN p')))->atClause() // => true
     * @example A set operator does not begin one
     *     (new \App\Gql\Parsing\Parser(\App\Gql\Parsing\TokenReader::of('UNION ALL')))->atClause() // => false
     *
     * @return bool True when the next word names a clause
     */
    public function atClause(): bool
    {
        foreach (self::CLAUSES as $keyword) {
            if ($this->tokens->atKeyword($keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reads one clause.
     *
     * @example Each clause is read by the word that names it
     *     $reader = \App\Gql\Parsing\TokenReader::of('OPTIONAL MATCH (p)');
     *     $parsed = (new \App\Gql\Parsing\Parser($reader))->parseClause();
     *     $parsed instanceof \App\Gql\Syntax\Clause\MatchClause ? $parsed->optional : null // => true
     * @example A statement GQL defines and peq does not run is refused by name
     *     (new \App\Gql\Parsing\Parser(\App\Gql\Parsing\TokenReader::of('DELETE (p)')))->parseClause() // throws \App\Gql\GqlException: does not run one
     *
     * @return Clause The clause
     *
     * @throws GqlException If what is written is not a clause, or is one peq does not run
     */
    public function parseClause(): Clause
    {
        if ($this->tokens->acceptKeyword('OPTIONAL')) {
            return $this->parseMatch(true);
        }
        if ($this->tokens->atKeyword('MATCH')) {
            return $this->parseMatch(false);
        }
        if ($this->tokens->atKeyword('LET')) {
            return $this->parseLet();
        }
        if ($this->tokens->atKeyword('FILTER')) {
            return $this->parseFilter();
        }
        if ($this->tokens->atKeyword('ORDER')) {
            return $this->results->parseOrderBy();
        }
        if ($this->tokens->atKeyword('RETURN')) {
            return $this->results->parse();
        }
        StatementRefusal::reject($this->tokens);

        return $this->parsePage();
    }

    /**
     * Reads a clause that looks for a shape in the graph.
     *
     * @param bool $optional Whether a row that matches nothing is kept rather than dropped
     *
     * @example A match can be narrowed after the pattern as well as inside it
     *     $reader = \App\Gql\Parsing\TokenReader::of('MATCH (p:Method) WHERE p.visibility = \'public\'');
     *     (new \App\Gql\Parsing\Parser($reader))->parseMatch(false)->where !== null // => true
     *
     * @return MatchClause The clause
     *
     * @throws GqlException If what is written is not a match
     */
    public function parseMatch(bool $optional): MatchClause
    {
        $this->tokens->expectKeyword('MATCH');
        $pattern = $this->patterns->parseGraph();
        $where = $this->tokens->acceptKeyword('WHERE') ? $this->expressions->parse() : null;

        return new MatchClause($pattern, $where, $optional);
    }

    /**
     * Reads a clause that names computed values.
     *
     * @example A clause can name several values at once
     *     $reader = \App\Gql\Parsing\TokenReader::of('LET a = p.line, b = p.name');
     *     count((new \App\Gql\Parsing\Parser($reader))->parseLet()->bindings) // => 2
     *
     * @return LetClause The clause
     *
     * @throws GqlException If what is written is not a naming
     */
    public function parseLet(): LetClause
    {
        $this->tokens->expectKeyword('LET');

        $bindings = [];
        do {
            $name = NameReader::variable($this->tokens);
            $this->tokens->expectSymbol('=');
            $bindings[] = new VariableBinding($name, $this->expressions->parse());
        } while ($this->tokens->acceptSymbol(','));

        return new LetClause($bindings);
    }

    /**
     * Reads a clause that keeps only the rows a predicate holds of.
     *
     * The `WHERE` after `FILTER` is optional, as GQL allows, and means nothing either
     * way: it is there for readers who find `FILTER WHERE` clearer.
     *
     * @example A filter reads the same with or without the optional word
     *     $reader = \App\Gql\Parsing\TokenReader::of('FILTER WHERE p.line > 10');
     *     (new \App\Gql\Parsing\Parser($reader))->parseFilter() instanceof \App\Gql\Syntax\Clause\FilterClause // => true
     *
     * @return FilterClause The clause
     *
     * @throws GqlException If what is written is not a filter
     */
    public function parseFilter(): FilterClause
    {
        $this->tokens->expectKeyword('FILTER');
        $this->tokens->acceptKeyword('WHERE');

        return new FilterClause($this->expressions->parse());
    }

    /**
     * Reads a clause that takes a stretch of the rows.
     *
     * @example A stretch can be written as an offset, a limit, or both
     *     $reader = \App\Gql\Parsing\TokenReader::of('OFFSET 5 LIMIT 10');
     *     (new \App\Gql\Parsing\Parser($reader))->parsePage()->limit // => 10
     *
     * @return PageClause The clause
     *
     * @throws GqlException If what is written is not a stretch
     */
    public function parsePage(): PageClause
    {
        $page = $this->results->parsePage();
        if ($page === null) {
            $this->tokens->fail('a clause');
        }

        return $page;
    }

    /**
     * Reads the operator combining this run of clauses with the next, if there is one.
     *
     * `UNION` written alone drops the rows that repeat, and `UNION ALL` keeps them.
     * Writing `UNION DISTINCT` says the first out loud, which the standard allows.
     *
     * @example Keeping every row is asked for in full
     *     (new \App\Gql\Parsing\Parser(\App\Gql\Parsing\TokenReader::of('UNION ALL MATCH (p)')))->parseSetOperator() // => \App\Gql\Syntax\SetOperator::UnionAll
     * @example Dropping the rows that repeat is the plain form
     *     (new \App\Gql\Parsing\Parser(\App\Gql\Parsing\TokenReader::of('UNION MATCH (p)')))->parseSetOperator() // => \App\Gql\Syntax\SetOperator::Union
     * @example A query that combines nothing has no operator
     *     (new \App\Gql\Parsing\Parser(\App\Gql\Parsing\TokenReader::of('')))->parseSetOperator() // => null
     *
     * @return null|SetOperator The operator, or null when the query combines nothing
     */
    public function parseSetOperator(): ?SetOperator
    {
        if ($this->tokens->acceptKeyword('UNION')) {
            if ($this->tokens->acceptKeyword('ALL')) {
                return SetOperator::UnionAll;
            }
            $this->tokens->acceptKeyword('DISTINCT');

            return SetOperator::Union;
        }
        if ($this->tokens->acceptKeyword('EXCEPT')) {
            return SetOperator::Except;
        }
        if ($this->tokens->acceptKeyword('INTERSECT')) {
            return SetOperator::Intersect;
        }

        return $this->tokens->acceptKeyword('OTHERWISE') ? SetOperator::Otherwise : null;
    }
}
