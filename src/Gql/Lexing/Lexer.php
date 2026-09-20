<?php

declare(strict_types=1);

namespace App\Gql\Lexing;

use App\Gql\GqlException;

/**
 * Reads a query text into the pieces a grammar is written over.
 *
 * The split between reading pieces and reading grammar is worth keeping sharp,
 * because the two make different mistakes. A query that says `RETRUN` is a sequence
 * of perfectly good pieces in an order no grammar allows; a query with an unclosed
 * string is not a sequence at all. Reported separately, each says what it is.
 *
 * Two decisions here are what make GQL's shape possible. Keywords are not recognised
 * at all — `MATCH` and a variable called `match` are the same piece, and which one
 * was meant is settled by where it stands — so a query can name a property `end`
 * without the grammar taking it for the end of a CASE. And the arrows of a graph
 * pattern are not read as single pieces: `<` and `-` arrive separately, so that
 * `a < -1` stays an arithmetic comparison and `<-[` stays an edge.
 *
 * @visibility App\Gql
 */
final class Lexer
{
    /**
     * The operators written as two characters, longest-match first.
     *
     * GQL spells inequality `<>` and nothing else. The `!=` that most languages also
     * accept is deliberately not here: a reader who writes it is told it cannot be
     * written, which is a smaller surprise than learning later that peq accepts a
     * spelling the language does not.
     */
    private const PAIRS = ['<>', '<=', '>=', '||'];

    /**
     * The operators and punctuation written as one character.
     */
    private const SINGLES = ['(', ')', '[', ']', '{', '}', ',', '.', ':', '=', '<', '>', '+', '-', '*', '/', '|', '&', '!', '%'];

    /**
     * @param SourceCursor $cursor The place in the query text reading continues from
     * @param string       $source The whole query text, kept so a piece of it can be quoted back
     */
    public function __construct(
        private readonly SourceCursor $cursor,
        private readonly string $source = '',
    ) {}

    /**
     * Prepares to read a query text.
     *
     * @param string $source The query, as it was written
     *
     * @example A query is read from its beginning
     *     \App\Gql\Lexing\Lexer::over('MATCH (p)')->tokenize()->current()->value // => 'MATCH'
     *
     * @return self A lexer standing at the start of it
     */
    public static function over(string $source): self
    {
        return new self(new SourceCursor($source), $source);
    }

    /**
     * Reads the whole query into its pieces.
     *
     * The list always ends with a piece standing for the end of the query, so a
     * grammar never has to ask whether there is anything left before asking what
     * comes next.
     *
     * @example A query reads as its pieces, ending with its end
     *     $pieces = \App\Gql\Lexing\Lexer::over('LIMIT 10')->tokenize();
     *     $pieces->at(2)->kind // => \App\Gql\Lexing\TokenKind::End
     *
     * @return TokenList The pieces the query is written from
     *
     * @throws GqlException If the text holds something that is not a piece of GQL
     */
    public function tokenize(): TokenList
    {
        $tokens = [];
        do {
            $token = $this->next();
            $tokens[] = $token;
        } while ($token->kind !== TokenKind::End);

        return new TokenList($tokens, $this->source);
    }

    /**
     * Reads the next piece of the query.
     *
     * @example Reading skips whatever stands between pieces
     *     \App\Gql\Lexing\Lexer::over('  -- a note' . "\n" . 'LIMIT')->next()->value // => 'LIMIT'
     *
     * @return Token The piece read, or the end of the query
     *
     * @throws GqlException If the text holds something that is not a piece of GQL
     */
    public function next(): Token
    {
        $this->skipTrivia();
        if ($this->cursor->atEnd()) {
            return new Token(TokenKind::End, '', '', $this->cursor->line(), $this->cursor->column(), $this->cursor->offset());
        }

        $next = $this->cursor->peek();
        if ($next === '`') {
            return QuotedScanner::name($this->cursor);
        }
        if ($next === "'" || $next === '"') {
            return QuotedScanner::text($this->cursor);
        }
        if (preg_match('/[0-9]/', $next) === 1) {
            return $this->scanNumber();
        }
        if (preg_match('/[A-Za-z_]/', $next) === 1) {
            return $this->scanName();
        }

        return $this->scanSymbol();
    }

    /**
     * Moves past whatever stands between two pieces of a query.
     *
     * GQL writes notes to a reader three ways — the two line forms it inherits from C
     * and from SQL, and the block form — and none of them is part of the query. A
     * block that is never closed runs to the end of the text rather than being
     * reported: what follows it was written as a note, and reading it as a query
     * would report a mistake the reader did not make.
     *
     * @example Whitespace, line notes and block notes all stand between pieces
     *     \App\Gql\Lexing\Lexer::over('/* a note *' . '/ LIMIT')->next()->value // => 'LIMIT'
     */
    public function skipTrivia(): void
    {
        while (!$this->cursor->atEnd()) {
            $spaces = $this->cursor->capture('/[ \t\r\n]+/A');
            if ($spaces !== null) {
                $this->cursor->take(strlen($spaces));

                continue;
            }
            if ($this->cursor->matches('//') || $this->cursor->matches('--')) {
                $this->cursor->take(strlen($this->cursor->capture('/[^\n]*/A') ?? ''));

                continue;
            }
            if (!$this->cursor->matches('/*')) {
                return;
            }
            $block = $this->cursor->capture('/\/\*.*?(\*\/|$)/As');
            $this->cursor->take(strlen($block ?? ''));
        }
    }

    /**
     * Reads a bare name: a variable, a label, a property, or a word a grammar reserves.
     *
     * @example A name is read as far as a name can go
     *     \App\Gql\Lexing\Lexer::over('firstName || x')->next()->value // => 'firstName'
     *
     * @return Token The name
     */
    public function scanName(): Token
    {
        $line = $this->cursor->line();
        $column = $this->cursor->column();
        $offset = $this->cursor->offset();
        $written = $this->cursor->capture('/[A-Za-z_][A-Za-z0-9_]*/A') ?? '';

        return new Token(TokenKind::Name, $this->cursor->take(strlen($written)), $written, $line, $column, $offset);
    }

    /**
     * Reads a number.
     *
     * A number with a fractional part, an exponent or a `d` or `f` suffix is a
     * decimal one; anything else is whole. The suffix is how GQL writes a literal it
     * wants treated as approximate even where it looks exact, and reading it here is
     * what keeps `1.0d` from arriving at the grammar as two pieces.
     *
     * @example A whole number is read as one
     *     \App\Gql\Lexing\Lexer::over('42')->next()->kind // => \App\Gql\Lexing\TokenKind::Integer
     * @example A number with a suffix is approximate
     *     \App\Gql\Lexing\Lexer::over('1.0d')->next()->kind // => \App\Gql\Lexing\TokenKind::Decimal
     *
     * @return Token The number
     */
    public function scanNumber(): Token
    {
        $line = $this->cursor->line();
        $column = $this->cursor->column();
        $offset = $this->cursor->offset();
        $written = $this->cursor->capture('/[0-9]+(\.[0-9]+)?([eE][+-]?[0-9]+)?[dDfF]?/A') ?? '';
        $exact = preg_match('/^[0-9]+$/', $written) === 1;

        return new Token(
            $exact ? TokenKind::Integer : TokenKind::Decimal,
            $this->cursor->take(strlen($written)),
            $written,
            $line,
            $column,
            $offset,
        );
    }

    /**
     * Reads an operator or a piece of punctuation.
     *
     * @example An operator written as two characters is read as one piece
     *     \App\Gql\Lexing\Lexer::over('<> 3')->next()->lexeme // => '<>'
     *
     * @return Token The symbol
     *
     * @throws GqlException If the character is not one GQL writes
     */
    public function scanSymbol(): Token
    {
        $line = $this->cursor->line();
        $column = $this->cursor->column();
        $offset = $this->cursor->offset();

        foreach (self::PAIRS as $pair) {
            if ($this->cursor->matches($pair)) {
                return new Token(TokenKind::Symbol, $this->cursor->take(2), $pair, $line, $column, $offset);
            }
        }

        $next = $this->cursor->peek();
        if (!in_array($next, self::SINGLES, true)) {
            throw GqlException::syntax('a query cannot be written with this character', $line, $column, '"'.$next.'"');
        }

        return new Token(TokenKind::Symbol, $this->cursor->take(1), $next, $line, $column, $offset);
    }
}
