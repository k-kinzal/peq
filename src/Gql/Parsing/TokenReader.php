<?php

declare(strict_types=1);

namespace App\Gql\Parsing;

use App\Gql\GqlException;
use App\Gql\Lexing\Lexer;
use App\Gql\Lexing\Token;
use App\Gql\Lexing\TokenKind;
use App\Gql\Lexing\TokenList;

/**
 * Reading the pieces of a query, with the small vocabulary a grammar needs.
 *
 * Three parsers share this: one for clauses, one for expressions, one for patterns.
 * They share it because they share a position in the query — a pattern's `WHERE` is
 * an expression, and where the expression stops is where the pattern goes on — and
 * because they should make the same mistake the same way.
 *
 * Every expectation that is not met is reported in one form: what was expected, what
 * was found, and where. That is the whole error handling of the grammar, and it is
 * here so that no parser has to invent its own.
 *
 * @visibility App\Gql
 */
final class TokenReader
{
    /**
     * @param TokenList $tokens The pieces of the query, with the text they were read from
     */
    public function __construct(
        private readonly TokenList $tokens,
    ) {}

    /**
     * Prepares to read a query text.
     *
     * @param string $source The query, as it was written
     *
     * @example A reader starts at the first piece of the query
     *     \App\Gql\Parsing\TokenReader::of('MATCH (p)')->current()->value // => 'MATCH'
     *
     * @return self A reader standing at the start of it
     *
     * @throws GqlException If the text holds something that is not a piece of GQL
     */
    public static function of(string $source): self
    {
        return new self(Lexer::over($source)->tokenize());
    }

    /**
     * Returns the piece the reading stands at.
     *
     * @example The piece read first is the first one written
     *     \App\Gql\Parsing\TokenReader::of('LIMIT 10')->current()->value // => 'LIMIT'
     *
     * @return Token The current piece
     */
    public function current(): Token
    {
        return $this->tokens->current();
    }

    /**
     * Returns a piece further on without reading up to it.
     *
     * @param int $ahead How many pieces past the current one to look
     *
     * @example Looking ahead is how a path variable is told from a plain one
     *     \App\Gql\Parsing\TokenReader::of('p = (a)')->peek()->lexeme // => '='
     *
     * @return Token The piece there, or the end of the query
     */
    public function peek(int $ahead = 1): Token
    {
        return $this->tokens->peek($ahead);
    }

    /**
     * Returns the piece the reading stands at and moves on.
     *
     * @example Taking a piece moves the reading on
     *     $reader = \App\Gql\Parsing\TokenReader::of('LIMIT 10');
     *     $reader->take();
     *     $reader->current()->value // => '10'
     *
     * @return Token The piece that was current
     */
    public function take(): Token
    {
        return $this->tokens->take();
    }

    /**
     * Returns how far the reading has got.
     *
     * @example A query that has not been read is at its start
     *     \App\Gql\Parsing\TokenReader::of('LIMIT 10')->position() // => 0
     *
     * @return int How many pieces have been read
     */
    public function position(): int
    {
        return $this->tokens->position();
    }

    /**
     * Puts the reading back to a place it has been.
     *
     * @param int $position How many pieces should count as read
     *
     * @example Reading can be put back to where it was
     *     $reader = \App\Gql\Parsing\TokenReader::of('LIMIT 10');
     *     $reader->take();
     *     $reader->seek(0);
     *     $reader->current()->value // => 'LIMIT'
     */
    public function seek(int $position): void
    {
        $this->tokens->seek($position);
    }

    /**
     * Returns the query text read since a place, as the reader wrote it.
     *
     * @param int $from The place the text starts at
     *
     * @example A column takes its heading from the text that produced it
     *     $reader = \App\Gql\Parsing\TokenReader::of('RETURN p.firstName');
     *     $reader->take();
     *     $reader->take();
     *     $reader->take();
     *     $reader->take();
     *     $reader->textSince(1) // => 'p.firstName'
     *
     * @return string The text between there and here
     */
    public function textSince(int $from): string
    {
        return $this->tokens->textBetween($from, $this->tokens->position());
    }

    /**
     * Reports whether the reading stands at a given piece of punctuation.
     *
     * @param string $lexeme The symbol to test for
     *
     * @example A reader knows what it is standing at
     *     \App\Gql\Parsing\TokenReader::of('(p)')->atSymbol('(') // => true
     *
     * @return bool True when it does
     */
    public function atSymbol(string $lexeme): bool
    {
        return $this->current()->isSymbol($lexeme);
    }

    /**
     * Reports whether the reading stands at a given keyword.
     *
     * @param string $keyword The keyword to test for
     *
     * @example A keyword is recognised however it is cased
     *     \App\Gql\Parsing\TokenReader::of('match (p)')->atKeyword('MATCH') // => true
     *
     * @return bool True when it does
     */
    public function atKeyword(string $keyword): bool
    {
        return $this->current()->isKeyword($keyword);
    }

    /**
     * Reports whether the reading stands at a name.
     *
     * @example A variable is a name
     *     \App\Gql\Parsing\TokenReader::of('p.firstName')->atName() // => true
     * @example A piece of punctuation is not
     *     \App\Gql\Parsing\TokenReader::of('(p)')->atName() // => false
     *
     * @return bool True when it does
     */
    public function atName(): bool
    {
        $kind = $this->current()->kind;

        return $kind === TokenKind::Name || $kind === TokenKind::QuotedName;
    }

    /**
     * Moves past a piece of punctuation if it is the one standing here.
     *
     * @param string $lexeme The symbol to move past
     *
     * @example An optional symbol is taken when it is there
     *     $reader = \App\Gql\Parsing\TokenReader::of(', p');
     *     $reader->acceptSymbol(',') // => true
     * @example And left alone when it is not
     *     \App\Gql\Parsing\TokenReader::of('p')->acceptSymbol(',') // => false
     *
     * @return bool True when it was there and was taken
     */
    public function acceptSymbol(string $lexeme): bool
    {
        if (!$this->atSymbol($lexeme)) {
            return false;
        }
        $this->take();

        return true;
    }

    /**
     * Moves past a keyword if it is the one standing here.
     *
     * @param string $keyword The keyword to move past
     *
     * @example An optional keyword is taken when it is there
     *     $reader = \App\Gql\Parsing\TokenReader::of('DISTINCT p');
     *     $reader->acceptKeyword('DISTINCT') // => true
     *
     * @return bool True when it was there and was taken
     */
    public function acceptKeyword(string $keyword): bool
    {
        if (!$this->atKeyword($keyword)) {
            return false;
        }
        $this->take();

        return true;
    }

    /**
     * Moves past a piece of punctuation, reporting the query wrong if it is not there.
     *
     * @param string $lexeme The symbol the grammar requires here
     *
     * @example A required symbol is taken
     *     \App\Gql\Parsing\TokenReader::of('(p)')->expectSymbol('(')->lexeme // => '('
     * @example One that is missing says so, and says where
     *     \App\Gql\Parsing\TokenReader::of('p)')->expectSymbol('(') // throws \App\Gql\GqlException: syntax error
     *
     * @return Token The symbol
     *
     * @throws GqlException If the query does not have it here
     */
    public function expectSymbol(string $lexeme): Token
    {
        if (!$this->atSymbol($lexeme)) {
            $this->fail('"'.$lexeme.'"');
        }

        return $this->take();
    }

    /**
     * Moves past a keyword, reporting the query wrong if it is not there.
     *
     * @param string $keyword The keyword the grammar requires here
     *
     * @example A required keyword is taken
     *     \App\Gql\Parsing\TokenReader::of('BY x')->expectKeyword('BY')->value // => 'BY'
     *
     * @return Token The keyword
     *
     * @throws GqlException If the query does not have it here
     */
    public function expectKeyword(string $keyword): Token
    {
        if (!$this->atKeyword($keyword)) {
            $this->fail($keyword);
        }

        return $this->take();
    }

    /**
     * Moves past a name, reporting the query wrong if there is not one here.
     *
     * @example A required name is taken
     *     \App\Gql\Parsing\TokenReader::of('firstName')->expectName() // => 'firstName'
     * @example A name in backticks is a name
     *     \App\Gql\Parsing\TokenReader::of('`return`')->expectName() // => 'return'
     *
     * @return string The name, as it stands for itself
     *
     * @throws GqlException If the query does not have one here
     */
    public function expectName(): string
    {
        if (!$this->atName()) {
            $this->fail('a name');
        }

        return $this->take()->value;
    }

    /**
     * Reports the query wrong here, saying what was wanted and what was written.
     *
     * @param string $expected What the grammar wanted at this place
     *
     * @example A query is told what it was missing and where
     *     \App\Gql\Parsing\TokenReader::of('RETURN')->fail('an expression') // throws \App\Gql\GqlException: an expression
     *
     * @throws GqlException Always
     */
    public function fail(string $expected): never
    {
        $token = $this->current();

        throw GqlException::syntax('expected '.$expected, $token->line, $token->column, $token->describe());
    }
}
