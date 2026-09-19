<?php

declare(strict_types=1);

namespace App\Gql\Lexing;

/**
 * One piece of a query text, with where it was written.
 *
 * A token carries two forms of itself. The lexeme is what the query said, and it is
 * what an error message quotes back so the reader recognises their own text. The
 * value is what it means: the characters inside a string once its escapes are
 * resolved, the name inside a pair of backticks. Keeping both is what lets a message
 * say `found "\n"` while the engine works with a newline.
 */
final class Token
{
    /**
     * @param TokenKind $kind   What kind of thing it is
     * @param string    $lexeme The text exactly as the query wrote it
     * @param string    $value  What that text stands for
     * @param int       $line   The line it starts on, counting from one
     * @param int       $column The column it starts at, counting from one
     * @param int       $offset How far into the query text it starts
     */
    public function __construct(
        public readonly TokenKind $kind,
        public readonly string $lexeme,
        public readonly string $value,
        public readonly int $line,
        public readonly int $column,
        public readonly int $offset = 0,
    ) {
        assert($this->line > 0, 'A token is written on a line, counted from one');
        assert($this->column > 0, 'A token is written at a column, counted from one');
    }

    /**
     * Reports whether this token is a particular piece of punctuation or operator.
     *
     * @param string $lexeme The symbol to test for, as it is written
     *
     * @example A symbol is recognised by what it is written as
     *     (new \App\Gql\Lexing\Token(\App\Gql\Lexing\TokenKind::Symbol, '(', '(', 1, 1))->isSymbol('(') // => true
     * @example A name that happens to read like one is not a symbol
     *     (new \App\Gql\Lexing\Token(\App\Gql\Lexing\TokenKind::Name, 'p', 'p', 1, 1))->isSymbol('p') // => false
     *
     * @return bool True when this token is that symbol
     */
    public function isSymbol(string $lexeme): bool
    {
        return $this->kind === TokenKind::Symbol && $this->lexeme === $lexeme;
    }

    /**
     * Reports whether this token is a bare name spelling a given keyword.
     *
     * GQL keywords are matched without regard to case, and a name written in
     * backticks is never one, which is how a query talks about a property called
     * `end` without the grammar taking it for the end of a CASE.
     *
     * @param string $keyword The keyword to test for
     *
     * @example A keyword is recognised however it is cased
     *     (new \App\Gql\Lexing\Token(\App\Gql\Lexing\TokenKind::Name, 'match', 'match', 1, 1))->isKeyword('MATCH') // => true
     * @example A name written in backticks is a name
     *     (new \App\Gql\Lexing\Token(\App\Gql\Lexing\TokenKind::QuotedName, '`match`', 'match', 1, 1))->isKeyword('MATCH') // => false
     *
     * @return bool True when this token spells that keyword
     */
    public function isKeyword(string $keyword): bool
    {
        return $this->kind === TokenKind::Name && strcasecmp($this->value, $keyword) === 0;
    }

    /**
     * Describes the token the way an error message quotes it back.
     *
     * @example A token is quoted as the reader wrote it
     *     (new \App\Gql\Lexing\Token(\App\Gql\Lexing\TokenKind::Name, 'RETRUN', 'RETRUN', 1, 1))->describe() // => '"RETRUN"'
     * @example The end of a query has nothing to quote
     *     (new \App\Gql\Lexing\Token(\App\Gql\Lexing\TokenKind::End, '', '', 1, 1))->describe() // => 'the end of the query'
     *
     * @return string The token, as an error message shows it
     */
    public function describe(): string
    {
        if ($this->kind === TokenKind::End) {
            return 'the end of the query';
        }

        return '"'.$this->lexeme.'"';
    }
}
