<?php

declare(strict_types=1);

namespace App\Gql\Parsing;

use App\Gql\Datum\DecimalDatum;
use App\Gql\GqlException;
use App\Gql\Lexing\TokenKind;
use App\Gql\Syntax\Clause\OrderByClause;
use App\Gql\Syntax\Clause\PageClause;
use App\Gql\Syntax\Clause\Projection;
use App\Gql\Syntax\Clause\ReturnClause;
use App\Gql\Syntax\Clause\SortDirection;
use App\Gql\Syntax\Clause\SortKey;
use App\Gql\Syntax\Expression;
use App\Gql\Syntax\Expression\VariableExpression;

/**
 * Reads what a query shows the reader.
 *
 * `RETURN` carries more than any other clause, because GQL puts the grouping, the
 * ordering and the paging of the result inside it rather than after it. Reading it
 * here keeps the clause reader short and keeps the one place that has to know the
 * order those parts are written in — columns, grouping, ordering, paging — to one
 * method.
 *
 * @visibility App\Gql\Parsing
 */
final readonly class ResultParser
{
    /**
     * @param TokenReader      $tokens      The pieces of the query being read
     * @param ExpressionParser $expressions Where a column, a sort key or a group key is read
     */
    public function __construct(
        private TokenReader $tokens,
        private ExpressionParser $expressions,
    ) {}

    /**
     * Reads what the reader is shown, with how it is grouped, ordered and paged.
     *
     * @example A projection names the columns it shows
     *     $reader = \App\Gql\Parsing\TokenReader::of('RETURN p.name AS symbol');
     *     $parsed = (new \App\Gql\Parsing\ResultParser($reader, new \App\Gql\Parsing\ExpressionParser($reader)))->parse();
     *     $parsed->columns[0]->heading() // => 'symbol'
     * @example Asking for everything names no column at all
     *     $reader = \App\Gql\Parsing\TokenReader::of('RETURN *');
     *     $parsed = (new \App\Gql\Parsing\ResultParser($reader, new \App\Gql\Parsing\ExpressionParser($reader)))->parse();
     *     $parsed->everything() // => true
     *
     * @return ReturnClause What the reader is shown
     *
     * @throws GqlException If what is written is not a projection
     */
    public function parse(): ReturnClause
    {
        $this->tokens->expectKeyword('RETURN');
        $distinct = $this->tokens->acceptKeyword('DISTINCT');
        $columns = $this->tokens->acceptSymbol('*') ? [] : $this->parseColumns();
        $groupBy = $this->parseGroupBy();
        $orderBy = $this->tokens->atKeyword('ORDER') ? $this->parseOrderBy()->keys : [];
        $page = $this->parsePage();

        return new ReturnClause($columns, $distinct, $groupBy, $orderBy, $page);
    }

    /**
     * Reads the columns a projection shows.
     *
     * @example Columns are separated the way a list is
     *     $reader = \App\Gql\Parsing\TokenReader::of('p.name, p.kind');
     *     count((new \App\Gql\Parsing\ResultParser($reader, new \App\Gql\Parsing\ExpressionParser($reader)))->parseColumns()) // => 2
     *
     * @return list<Projection> The columns, in the order they are shown
     *
     * @throws GqlException If what is written is not a column
     */
    public function parseColumns(): array
    {
        $columns = [];
        do {
            $columns[] = $this->parseColumn();
        } while ($this->tokens->acceptSymbol(','));

        return $columns;
    }

    /**
     * Reads one column, and what it is headed by.
     *
     * A column written without a name is headed by the query text that produced it,
     * quoted exactly, so that a reader recognises their own writing in the result.
     *
     * @example A column with no name is headed by what produced it
     *     $reader = \App\Gql\Parsing\TokenReader::of("p.firstName || ' '");
     *     $parsed = (new \App\Gql\Parsing\ResultParser($reader, new \App\Gql\Parsing\ExpressionParser($reader)))->parseColumn();
     *     $parsed->heading() // => "p.firstName || ' '"
     *
     * @return Projection The column
     *
     * @throws GqlException If what is written is not a column
     */
    public function parseColumn(): Projection
    {
        $from = $this->tokens->position();
        $value = $this->expressions->parse();
        $written = $this->tokens->textSince($from);
        $alias = $this->tokens->acceptKeyword('AS') ? NameReader::identifier($this->tokens) : null;

        return new Projection($value, $alias, $written);
    }

    /**
     * Reads what the rows are grouped by, if they are grouped.
     *
     * ISO/IEC 39075 writes a `<grouping element>` as a binding variable reference and
     * nothing else, so a group key is a name: one a `LET` gave, or one the `RETURN`
     * gave with `AS`. `GROUP BY c.name` is not GQL; `RETURN c.name AS name ... GROUP BY
     * name` is, and says the same.
     *
     * @example Rows can be grouped by more than one name
     *     $reader = \App\Gql\Parsing\TokenReader::of('GROUP BY kind, owner');
     *     count((new \App\Gql\Parsing\ResultParser($reader, new \App\Gql\Parsing\ExpressionParser($reader)))->parseGroupBy()) // => 2
     * @example Anything but a name is not a group key
     *     $reader = \App\Gql\Parsing\TokenReader::of('GROUP BY owner.name');
     *     (new \App\Gql\Parsing\ResultParser($reader, new \App\Gql\Parsing\ExpressionParser($reader)))->parseGroupBy() // throws \App\Gql\GqlException: GROUP BY
     * @example A projection that does not group says nothing
     *     $reader = \App\Gql\Parsing\TokenReader::of('LIMIT 10');
     *     (new \App\Gql\Parsing\ResultParser($reader, new \App\Gql\Parsing\ExpressionParser($reader)))->parseGroupBy() // => []
     *
     * @return list<Expression> What the rows are grouped by
     *
     * @throws GqlException If what is written is not a list of names
     */
    public function parseGroupBy(): array
    {
        if (!$this->tokens->acceptKeyword('GROUP')) {
            return [];
        }
        $this->tokens->expectKeyword('BY');

        $keys = [];
        do {
            $keys[] = new VariableExpression(NameReader::variable($this->tokens));
            if ($this->tokens->atSymbol('.')) {
                $this->tokens->fail('a comma or the end of GROUP BY, which groups by names only');
            }
        } while ($this->tokens->acceptSymbol(','));

        return $keys;
    }

    /**
     * Reads an ordering.
     *
     * @example An ordering can have more than one key, tried in the order written
     *     $reader = \App\Gql\Parsing\TokenReader::of('ORDER BY a DESC, b');
     *     count((new \App\Gql\Parsing\ResultParser($reader, new \App\Gql\Parsing\ExpressionParser($reader)))->parseOrderBy()->keys) // => 2
     *
     * @return OrderByClause The ordering
     *
     * @throws GqlException If what is written is not an ordering
     */
    public function parseOrderBy(): OrderByClause
    {
        $this->tokens->expectKeyword('ORDER');
        $this->tokens->expectKeyword('BY');

        $keys = [];
        do {
            $value = $this->expressions->parse();
            $keys[] = new SortKey($value, $this->parseDirection());
        } while ($this->tokens->acceptSymbol(','));

        return new OrderByClause($keys);
    }

    /**
     * Reads which way a sort key orders, defaulting to the way GQL defaults to.
     *
     * @example A key that says nothing orders smallest first
     *     $reader = \App\Gql\Parsing\TokenReader::of(', b');
     *     (new \App\Gql\Parsing\ResultParser($reader, new \App\Gql\Parsing\ExpressionParser($reader)))->parseDirection() // => \App\Gql\Syntax\Clause\SortDirection::Ascending
     * @example A key can be written either short or long
     *     $reader = \App\Gql\Parsing\TokenReader::of('DESCENDING');
     *     (new \App\Gql\Parsing\ResultParser($reader, new \App\Gql\Parsing\ExpressionParser($reader)))->parseDirection() // => \App\Gql\Syntax\Clause\SortDirection::Descending
     *
     * @return SortDirection Which way it orders
     */
    public function parseDirection(): SortDirection
    {
        if ($this->tokens->acceptKeyword('DESC') || $this->tokens->acceptKeyword('DESCENDING')) {
            return SortDirection::Descending;
        }
        $this->tokens->acceptKeyword('ASC') || $this->tokens->acceptKeyword('ASCENDING');

        return SortDirection::Ascending;
    }

    /**
     * Reads which stretch of the result is shown, if only a stretch of it is.
     *
     * @example A projection can skip some rows and keep some
     *     $reader = \App\Gql\Parsing\TokenReader::of('OFFSET 10 LIMIT 5');
     *     (new \App\Gql\Parsing\ResultParser($reader, new \App\Gql\Parsing\ExpressionParser($reader)))->parsePage()?->limit // => 5
     * @example A projection that says nothing shows all of them
     *     $reader = \App\Gql\Parsing\TokenReader::of('');
     *     (new \App\Gql\Parsing\ResultParser($reader, new \App\Gql\Parsing\ExpressionParser($reader)))->parsePage() // => null
     *
     * @return null|PageClause The stretch, or null when the whole result is shown
     *
     * @throws GqlException If what is written is not a stretch
     */
    public function parsePage(): ?PageClause
    {
        $offset = 0;
        $skipped = $this->tokens->acceptKeyword('OFFSET') || $this->tokens->acceptKeyword('SKIP');
        if ($skipped) {
            $offset = $this->parseCount();
        }

        $limit = $this->tokens->acceptKeyword('LIMIT') ? $this->parseCount() : null;
        if (!$skipped && $limit === null) {
            return null;
        }

        return new PageClause($offset, $limit);
    }

    /**
     * Reads a count of rows.
     *
     * @example A count is a whole number of rows
     *     (new \App\Gql\Parsing\ResultParser($reader = \App\Gql\Parsing\TokenReader::of('10'), new \App\Gql\Parsing\ExpressionParser($reader)))->parseCount() // => 10
     * @example Anything else is reported rather than guessed at
     *     (new \App\Gql\Parsing\ResultParser($reader = \App\Gql\Parsing\TokenReader::of('many'), new \App\Gql\Parsing\ExpressionParser($reader)))->parseCount() // throws \App\Gql\GqlException: a whole number of rows
     *
     * @return int The count
     *
     * @throws GqlException If what is written is not a whole number of rows, or is too large for one
     */
    public function parseCount(): int
    {
        if ($this->tokens->current()->kind !== TokenKind::Integer) {
            $this->tokens->fail('a whole number of rows');
        }

        return DecimalDatum::whole($this->tokens->take()->value);
    }
}
