<?php

declare(strict_types=1);

namespace App\Gql\Lexing;

/**
 * The pieces of a query, read in order with the ability to look ahead.
 *
 * A grammar needs two things a plain list does not give it. It needs to look at what
 * comes next without committing to it — GQL decides between a path variable and a
 * plain one by what follows the name — and it needs to be able to go back, because a
 * parenthesis opens both a grouped pattern and a grouped expression.
 *
 * Reading past the end is not a mistake here. The list always ends with a piece
 * standing for the end of the query, and asking for anything beyond it is answered
 * with that same piece, so a grammar can ask what comes next without first asking
 * whether anything does.
 *
 * @visibility App\Gql
 */
final class TokenList
{
    /**
     * How many pieces have been read.
     */
    private int $position = 0;

    /**
     * @param list<Token> $tokens The pieces of the query, ending with its end
     * @param string      $source The query text they were read from
     */
    public function __construct(
        private readonly array $tokens,
        private readonly string $source = '',
    ) {
        assert($this->tokens !== [], 'A query is read as at least the piece standing for its end');
        assert($this->tokens[count($this->tokens) - 1]->kind === TokenKind::End, 'The last piece read from a query stands for its end');
    }

    /**
     * Returns the piece the reading stands at.
     *
     * @example Reading starts at the first piece
     *     \App\Gql\Lexing\Lexer::over('MATCH (p)')->tokenize()->current()->value // => 'MATCH'
     *
     * @return Token The current piece
     */
    public function current(): Token
    {
        return $this->at($this->position);
    }

    /**
     * Returns a piece further on without reading up to it.
     *
     * @param int $ahead How many pieces past the current one to look
     *
     * @example Looking ahead does not move the reading on
     *     $pieces = \App\Gql\Lexing\Lexer::over('p = (a)')->tokenize();
     *     $pieces->peek()->lexeme // => '='
     *     $pieces->current()->value // => 'p'
     *
     * @return Token The piece there, or the end of the query
     */
    public function peek(int $ahead = 1): Token
    {
        return $this->at($this->position + $ahead);
    }

    /**
     * Returns the piece at a place in the query, counting from its start.
     *
     * @param int $index How many pieces into the query to look
     *
     * @example A query ends with the piece standing for its end
     *     \App\Gql\Lexing\Lexer::over('LIMIT 10')->tokenize()->at(2)->kind // => \App\Gql\Lexing\TokenKind::End
     * @example Asking past the end is answered with the end
     *     \App\Gql\Lexing\Lexer::over('LIMIT 10')->tokenize()->at(99)->kind // => \App\Gql\Lexing\TokenKind::End
     *
     * @return Token The piece there, or the end of the query
     */
    public function at(int $index): Token
    {
        $last = count($this->tokens) - 1;

        return $this->tokens[min(max($index, 0), $last)];
    }

    /**
     * Returns the piece the reading stands at and moves on to the next.
     *
     * The end of the query is never moved past, so a grammar that keeps reading
     * after it keeps being told the same thing rather than running off the list.
     *
     * @example Taking a piece moves the reading on
     *     $pieces = \App\Gql\Lexing\Lexer::over('MATCH (p)')->tokenize();
     *     $pieces->take();
     *     $pieces->current()->lexeme // => '('
     *
     * @return Token The piece that was current
     */
    public function take(): Token
    {
        $token = $this->current();
        if ($token->kind !== TokenKind::End) {
            ++$this->position;
        }

        return $token;
    }

    /**
     * Returns how far the reading has got.
     *
     * @example A query that has not been read is at its start
     *     \App\Gql\Lexing\Lexer::over('MATCH (p)')->tokenize()->position() // => 0
     *
     * @return int How many pieces have been read
     */
    public function position(): int
    {
        return $this->position;
    }

    /**
     * Puts the reading back to a place it has been.
     *
     * @param int $position How many pieces should count as read
     *
     * @example Reading can be put back to where it was
     *     $pieces = \App\Gql\Lexing\Lexer::over('MATCH (p)')->tokenize();
     *     $pieces->take();
     *     $pieces->seek(0);
     *     $pieces->current()->value // => 'MATCH'
     */
    public function seek(int $position): void
    {
        $this->position = min(max($position, 0), count($this->tokens) - 1);
    }

    /**
     * Returns the query text a stretch of pieces was read from.
     *
     * A result column that was not given a name is headed by the expression that
     * produced it, and the only faithful way to write that expression down is to
     * quote the query. Joining the pieces back up would put the spaces somewhere
     * else: `p.firstName || ' '` is not `p . firstName || ' '`.
     *
     * @param int $from The first piece of the stretch
     * @param int $to   The piece after the last one of it
     *
     * @example A stretch of a query reads back as the reader wrote it
     *     $pieces = \App\Gql\Lexing\Lexer::over("RETURN p.firstName || ' '")->tokenize();
     *     $pieces->textBetween(1, 6) // => "p.firstName || ' '"
     *
     * @return string The query text between them
     */
    public function textBetween(int $from, int $to): string
    {
        $start = $this->at($from)->offset;
        $end = $this->at($to)->offset;
        if ($end <= $start) {
            return '';
        }

        return trim(substr($this->source, $start, $end - $start));
    }
}
