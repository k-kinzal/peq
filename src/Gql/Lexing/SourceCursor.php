<?php

declare(strict_types=1);

namespace App\Gql\Lexing;

/**
 * A place in a query text, and the way forward from it.
 *
 * Reading a query means moving through it one piece at a time while remembering
 * where each piece started, so that a mistake can be pointed at rather than merely
 * reported. Keeping that bookkeeping in one place is what lets the lexer be written
 * as a set of small readers, none of which has to remember to count a newline.
 *
 * @visibility App\Gql\Lexing
 */
final class SourceCursor
{
    /**
     * How many characters of the text have been consumed.
     */
    private int $offset = 0;

    /**
     * The line the cursor stands on, counting from one.
     */
    private int $line = 1;

    /**
     * The column the cursor stands at, counting from one.
     */
    private int $column = 1;

    /**
     * @param string $text The query text to read
     */
    public function __construct(
        private readonly string $text,
    ) {}

    /**
     * Reports whether the whole text has been read.
     *
     * @example A cursor over nothing is already at the end
     *     (new \App\Gql\Lexing\SourceCursor(''))->atEnd() // => true
     *
     * @return bool True when nothing is left to read
     */
    public function atEnd(): bool
    {
        return $this->offset >= strlen($this->text);
    }

    /**
     * Looks at a character without consuming it.
     *
     * Looking past the end reads as nothing rather than as an error, because every
     * reader has to ask what comes next and the answer at the end is "nothing".
     *
     * @param int $ahead How many characters past the cursor to look
     *
     * @example The next character is the one the cursor stands on
     *     (new \App\Gql\Lexing\SourceCursor('MATCH'))->peek() // => 'M'
     * @example Looking past the end reads as nothing
     *     (new \App\Gql\Lexing\SourceCursor('M'))->peek(1) // => ''
     *
     * @return string The character, or an empty string past the end
     */
    public function peek(int $ahead = 0): string
    {
        return $this->text[$this->offset + $ahead] ?? '';
    }

    /**
     * Reports whether the text continues with a given piece of writing.
     *
     * @param string $prefix The writing to test for
     *
     * @example A cursor knows what it is about to read
     *     (new \App\Gql\Lexing\SourceCursor('<= 3'))->matches('<=') // => true
     *
     * @return bool True when the text at the cursor starts with it
     */
    public function matches(string $prefix): bool
    {
        return substr($this->text, $this->offset, strlen($prefix)) === $prefix;
    }

    /**
     * Reads what a pattern matches at the cursor, without consuming it.
     *
     * The pattern is anchored at the cursor, so it describes what comes next rather
     * than what appears anywhere later in the query.
     *
     * @param string $pattern The pattern, written the way preg_match takes one
     *
     * @example A pattern reads only what stands at the cursor
     *     (new \App\Gql\Lexing\SourceCursor('total42 '))->capture('/[A-Za-z_][A-Za-z0-9_]{0,63}/A') // => 'total42'
     * @example A pattern that does not match reads nothing
     *     (new \App\Gql\Lexing\SourceCursor('42'))->capture('/[A-Za-z_][A-Za-z0-9_]{0,63}/A') // => null
     *
     * @return null|string What the pattern matched, or null when it did not match
     */
    public function capture(string $pattern): ?string
    {
        $matched = [];
        if (preg_match($pattern, $this->text, $matched, 0, $this->offset) !== 1) {
            return null;
        }

        return $matched[0];
    }

    /**
     * Consumes a number of characters and returns them.
     *
     * @param int $length How many characters to consume
     *
     * @example Taking moves the cursor past what it returns
     *     $cursor = new \App\Gql\Lexing\SourceCursor('MATCH (p)');
     *     $cursor->take(5) // => 'MATCH'
     *     $cursor->peek() // => ' '
     * @example A newline moves the cursor to the next line
     *     $cursor = new \App\Gql\Lexing\SourceCursor("a\nb");
     *     $cursor->take(2);
     *     $cursor->line() // => 2
     *
     * @return string The characters consumed
     */
    public function take(int $length): string
    {
        $taken = substr($this->text, $this->offset, $length);
        $this->offset += strlen($taken);

        $lines = substr_count($taken, "\n");
        if ($lines > 0) {
            $this->line += $lines;
            $this->column = self::characters(substr($taken, (int) strrpos($taken, "\n") + 1)) + 1;

            return $taken;
        }

        $this->column += self::characters($taken);

        return $taken;
    }

    /**
     * Counts the characters in a stretch of UTF-8, even one cut in the middle of a character.
     *
     * A character is counted at the byte that begins it, so a stretch that ends partway
     * through one still counts it once, and the rest of it counts nothing.
     *
     * @param string $bytes The stretch
     *
     * @example A character written in three bytes is one character
     *     \App\Gql\Lexing\SourceCursor::characters('顧客') // => 2
     *
     * @return int How many characters begin in it
     */
    public static function characters(string $bytes): int
    {
        return strlen($bytes) - (int) preg_match_all('/[\x80-\xBF]/', $bytes);
    }

    /**
     * Returns how far into the text the cursor stands.
     *
     * A token remembers this so that the query text behind it can be quoted back
     * exactly — which is what a result column headed by the expression that produced
     * it needs, and what no amount of re-joining the pieces would get right.
     *
     * @example A cursor starts at the beginning of the text
     *     (new \App\Gql\Lexing\SourceCursor('MATCH'))->offset() // => 0
     *
     * @return int How many characters have been consumed
     */
    public function offset(): int
    {
        return $this->offset;
    }

    /**
     * Returns the line the cursor stands on.
     *
     * @example A cursor starts on the first line
     *     (new \App\Gql\Lexing\SourceCursor('MATCH'))->line() // => 1
     *
     * @return int The line, counting from one
     */
    public function line(): int
    {
        return $this->line;
    }

    /**
     * Returns the column the cursor stands at.
     *
     * @example A cursor starts at the first column
     *     (new \App\Gql\Lexing\SourceCursor('MATCH'))->column() // => 1
     *
     * @return int The column, counting from one
     */
    public function column(): int
    {
        return $this->column;
    }
}
