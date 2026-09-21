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
    private const SINGLES = ['(', ')', '[', ']', '{', '}', ',', '.', ':', '=', '<', '>', '+', '-', '*', '/', '|', '&', '!', '%', '~'];

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
        if ($next === "'" || $next === '"' || ($next === '@' && in_array($this->cursor->peek(1), ["'", '"'], true))) {
            return QuotedScanner::text($this->cursor);
        }
        if (preg_match('/[0-9]/', $next) === 1 || ($next === '.' && preg_match('/[0-9]/', $this->cursor->peek(1)) === 1)) {
            return $this->scanNumber();
        }
        if ($this->cursor->capture('/[\p{L}\p{Nl}\p{Pc}]/Au') !== null) {
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
     * A name is not only ASCII. It starts with a letter or a connecting mark such as an
     * underscore, and goes on with letters, digits, combining marks and connecting
     * marks, so `顧客` is as good a variable as `customer`.
     *
     * @example A name is read as far as a name can go
     *     \App\Gql\Lexing\Lexer::over('firstName || x')->next()->value // => 'firstName'
     * @example A name may be written in any script
     *     \App\Gql\Lexing\Lexer::over('顧客.name')->next()->value // => '顧客'
     *
     * @return Token The name
     */
    public function scanName(): Token
    {
        $line = $this->cursor->line();
        $column = $this->cursor->column();
        $offset = $this->cursor->offset();
        $written = $this->cursor->capture('/[\p{L}\p{Nl}\p{Pc}][\p{L}\p{Nl}\p{Mn}\p{Mc}\p{Nd}\p{Pc}]*/Au') ?? '';

        return new Token(TokenKind::Name, $this->cursor->take(strlen($written)), $written, $line, $column, $offset);
    }

    /**
     * Reads a number.
     *
     * GQL tells exact numbers from approximate ones by how they are written, not by
     * what they are worth. A number with a point is exact, as is one marked with `M`;
     * one written with an exponent is approximate, as is one marked with `F` or `D`.
     * So `1.5` is exactly one and a half, and `1.5e0` is the nearest float. Reading the
     * suffix here is what keeps `1.0d` from arriving at the grammar as two pieces.
     *
     * Digits may be grouped with single underscores, as `1_000_000`, and the point may
     * come first or last, as `.5` and `5.` — GQL's `<unsigned decimal integer>` and
     * `<unsigned decimal in common notation>` write both. The value a number stands
     * for leaves the underscores out.
     *
     * @example A whole number is read as one
     *     \App\Gql\Lexing\Lexer::over('42')->next()->kind // => \App\Gql\Lexing\TokenKind::Integer
     * @example A point makes a number exact, not approximate
     *     \App\Gql\Lexing\Lexer::over('1.5')->next()->kind // => \App\Gql\Lexing\TokenKind::Decimal
     * @example An exponent makes it approximate
     *     \App\Gql\Lexing\Lexer::over('1.5e0')->next()->kind // => \App\Gql\Lexing\TokenKind::Approximate
     * @example So does a suffix
     *     \App\Gql\Lexing\Lexer::over('1.0d')->next()->kind // => \App\Gql\Lexing\TokenKind::Approximate
     * @example Digits may be grouped
     *     \App\Gql\Lexing\Lexer::over('1_000')->next()->value // => '1000'
     * @example And the point may come first
     *     \App\Gql\Lexing\Lexer::over('.5')->next()->kind // => \App\Gql\Lexing\TokenKind::Decimal
     *
     * @return Token The number
     */
    public function scanNumber(): Token
    {
        $line = $this->cursor->line();
        $column = $this->cursor->column();
        $offset = $this->cursor->offset();
        $digits = '[0-9](?:_?[0-9])*';
        $written = $this->cursor->capture('/(?:'.$digits.'(?:\.(?:'.$digits.')?)?|\.'.$digits.')(?:[eE][+-]?'.$digits.')?[dDfFmM]?/A') ?? '';
        $value = str_replace('_', '', $written);

        return new Token(
            self::numberKind($value),
            $this->cursor->take(strlen($written)),
            $value,
            $line,
            $column,
            $offset,
        );
    }

    /**
     * Tells which kind of number a literal is, by how it is written.
     *
     * @param string $written The literal
     *
     * @example An exponent without `M` makes a number approximate
     *     \App\Gql\Lexing\Lexer::numberKind('1e3') // => \App\Gql\Lexing\TokenKind::Approximate
     * @example `M` keeps it exact
     *     \App\Gql\Lexing\Lexer::numberKind('1e3M') // => \App\Gql\Lexing\TokenKind::Decimal
     *
     * @return TokenKind The kind
     */
    public static function numberKind(string $written): TokenKind
    {
        return match (true) {
            preg_match('/[dDfF]$/', $written) === 1, preg_match('/[eE]/', $written) === 1 && preg_match('/[mM]$/', $written) !== 1 => TokenKind::Approximate,
            preg_match('/^[0-9]+$/', $written) === 1 => TokenKind::Integer,
            default => TokenKind::Decimal,
        };
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
