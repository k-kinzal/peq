<?php

declare(strict_types=1);

namespace App\Gql\Lexing;

use App\Gql\GqlException;

/**
 * Reads the two things a query writes between delimiters: strings and quoted names.
 *
 * They are one reader because they are one problem. Both run to a closing delimiter,
 * both let that delimiter be written inside them, and both have to hand back two
 * forms of themselves — what was written, so an error can quote it, and what it
 * stands for, so the engine can use it.
 *
 * A string may escape with a backslash as well, with exactly the escapes GQL's
 * `<escaped character>` lists: `\\`, `\'`, `\"`, `\``, `\t`, `\b`, `\n`, `\r`, `\f`,
 * and a character by its code point as `\uXXXX` or `\UXXXXXX`. Any other character
 * after a backslash is not an escape, and a string written with one is refused rather
 * than read as if the backslash were not there. A string written after `@` has no
 * escapes at all. A quoted name has none either: a name is a name, and letting it carry
 * a newline would make two different declarations print identically.
 *
 * @visibility App\Gql\Lexing
 */
final class QuotedScanner
{
    /**
     * What each backslash escape in a string stands for.
     */
    private const array ESCAPES = [
        'n' => "\n",
        't' => "\t",
        'r' => "\r",
        'b' => "\x08",
        'f' => "\f",
        '\\' => '\\',
        "'" => "'",
        '"' => '"',
        '`' => '`',
    ];

    /**
     * Reads a character string written in single or double quotes.
     *
     * @param SourceCursor $cursor The place in the query the string starts at
     *
     * @example A string is read as the characters between its quotes
     *     \App\Gql\Lexing\QuotedScanner::text(new \App\Gql\Lexing\SourceCursor("'/users'"))->value // => '/users'
     * @example An escape stands for the character it names
     *     \App\Gql\Lexing\QuotedScanner::text(new \App\Gql\Lexing\SourceCursor('"a\\tb"'))->value // => "a\tb"
     * @example A doubled quote stands for one of itself
     *     \App\Gql\Lexing\QuotedScanner::text(new \App\Gql\Lexing\SourceCursor("'it''s'"))->value // => "it's"
     * @example A string after `@` has no escapes
     *     \App\Gql\Lexing\QuotedScanner::text(new \App\Gql\Lexing\SourceCursor("@'a\\tb'"))->value // => 'a\tb'
     * @example A backslash before anything GQL does not escape is refused
     *     \App\Gql\Lexing\QuotedScanner::text(new \App\Gql\Lexing\SourceCursor("'a\\qb'")) // throws \App\Gql\GqlException: escape
     *
     * @return Token The string, with what it was written as and what it stands for
     *
     * @throws GqlException If the string is never closed, or escapes something GQL does not escape
     */
    public static function text(SourceCursor $cursor): Token
    {
        $line = $cursor->line();
        $column = $cursor->column();
        $offset = $cursor->offset();
        $raw = $cursor->peek() === '@';
        $lexeme = $raw ? $cursor->take(1) : '';
        $quote = $cursor->take(1);
        $lexeme .= $quote;
        $value = '';

        while (!$cursor->atEnd()) {
            $next = $cursor->peek();
            if ($next === $quote && $cursor->peek(1) === $quote) {
                $lexeme .= $cursor->take(2);
                $value .= $quote;

                continue;
            }
            if ($next === $quote) {
                return new Token(TokenKind::Text, $lexeme.$cursor->take(1), $value, $line, $column, $offset);
            }
            if ($next === '\\' && !$raw) {
                [$written, $meant] = self::escape($cursor);
                $lexeme .= $written;
                $value .= $meant;

                continue;
            }
            $lexeme .= $cursor->take(1);
            $value .= $next;
        }

        throw GqlException::syntax('a character string is never closed', $line, $column, '"'.$lexeme.'"');
    }

    /**
     * Reads one escape in a string, from its backslash on.
     *
     * @param SourceCursor $cursor The place in the query the backslash stands at
     *
     * @example An escape by name stands for the character it names
     *     \App\Gql\Lexing\QuotedScanner::escape(new \App\Gql\Lexing\SourceCursor('\\n')) // => ['\\n', "\n"]
     * @example An escape by code point stands for that character
     *     \App\Gql\Lexing\QuotedScanner::escape(new \App\Gql\Lexing\SourceCursor('\\u00e9'))[1] // => 'é'
     *
     * @return array{string, string} What was written, and the character it stands for
     *
     * @throws GqlException If what follows the backslash is not an escape GQL writes
     */
    public static function escape(SourceCursor $cursor): array
    {
        $line = $cursor->line();
        $column = $cursor->column();
        $written = $cursor->take(1);
        $escape = $cursor->peek();
        $digits = ['u' => 4, 'U' => 6][$escape] ?? 0;
        if ($digits > 0) {
            $code = $cursor->capture('/[uU][0-9A-Fa-f]{'.$digits.'}/A');
            $point = $code === null ? false : mb_chr((int) hexdec(substr($code, 1)), 'UTF-8');
            if ($code === null || $point === false) {
                throw GqlException::syntax(sprintf('a \%s escape is followed by %d hexadecimal digits naming a character', $escape, $digits), $line, $column, '"'.$written.$escape.'"');
            }

            return [$written.$cursor->take(strlen($code)), $point];
        }
        if (!isset(self::ESCAPES[$escape])) {
            throw GqlException::syntax(
                'an escape GQL writes after a backslash — \\\, \\\', \", \`, \t, \b, \n, \r, \f, \u or \U',
                $line,
                $column,
                '"'.$written.$escape.'"',
            );
        }

        return [$written.$cursor->take(1), self::ESCAPES[$escape]];
    }

    /**
     * Reads a name written in backticks.
     *
     * Backticks are how a query names something that spells a keyword, or that
     * carries a character a bare name may not. A doubled backtick stands for one of
     * itself, which is the only escape a name has.
     *
     * @param SourceCursor $cursor The place in the query the name starts at
     *
     * @example A quoted name is the text between its backticks
     *     \App\Gql\Lexing\QuotedScanner::name(new \App\Gql\Lexing\SourceCursor('`return`'))->value // => 'return'
     * @example A doubled backtick stands for one of itself
     *     \App\Gql\Lexing\QuotedScanner::name(new \App\Gql\Lexing\SourceCursor('`a``b`'))->value // => 'a`b'
     *
     * @return Token The name, with what it was written as and what it stands for
     *
     * @throws GqlException If the name is never closed
     */
    public static function name(SourceCursor $cursor): Token
    {
        $line = $cursor->line();
        $column = $cursor->column();
        $offset = $cursor->offset();
        $lexeme = $cursor->take(1);
        $value = '';

        while (!$cursor->atEnd()) {
            if ($cursor->peek() === '`' && $cursor->peek(1) === '`') {
                $lexeme .= $cursor->take(2);
                $value .= '`';

                continue;
            }
            if ($cursor->peek() === '`') {
                return new Token(TokenKind::QuotedName, $lexeme.$cursor->take(1), $value, $line, $column, $offset);
            }
            $value .= $cursor->peek();
            $lexeme .= $cursor->take(1);
        }

        throw GqlException::syntax('a quoted name is never closed', $line, $column, '"'.$lexeme.'"');
    }
}
