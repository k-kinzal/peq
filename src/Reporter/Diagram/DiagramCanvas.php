<?php

declare(strict_types=1);

namespace App\Reporter\Diagram;

/**
 * A grid of characters a drawing is made on, with lines that join where they meet.
 *
 * Lines are not drawn as characters but recorded as strokes: which of its four sides
 * each cell is joined on, and for which arrow. The characters are chosen at the end,
 * once every stroke is known, so a lane that an arrow leaves from halfway down comes
 * out as `├` rather than whichever of `│` and `─` happened to be drawn last.
 *
 * Two arrows that merely cross do not join. A cell where one arrow passes straight
 * down and a different one straight across is drawn as the vertical line, unbroken,
 * so that a crossing is never read as a turn.
 *
 * A line that holds nothing but vertical lines is left out. It only ever separates
 * places that fan out from one another, and leaving it out shortens the drawing
 * without changing what joins what.
 *
 * @visibility App\Reporter\Diagram
 */
final class DiagramCanvas
{
    /**
     * A cell joined to the one above.
     */
    public const int NORTH = 1;

    /**
     * A cell joined to the one on its right.
     */
    public const int EAST = 2;

    /**
     * A cell joined to the one below.
     */
    public const int SOUTH = 4;

    /**
     * A cell joined to the one on its left.
     */
    public const int WEST = 8;

    /**
     * The character for each way a cell can be joined.
     */
    public const array GLYPHS = [
        0 => ' ',
        self::NORTH => '│',
        self::SOUTH => '│',
        self::NORTH | self::SOUTH => '│',
        self::EAST => '─',
        self::WEST => '─',
        self::EAST | self::WEST => '─',
        self::SOUTH | self::EAST => '┌',
        self::SOUTH | self::WEST => '┐',
        self::NORTH | self::EAST => '└',
        self::NORTH | self::WEST => '┘',
        self::NORTH | self::SOUTH | self::EAST => '├',
        self::NORTH | self::SOUTH | self::WEST => '┤',
        self::EAST | self::WEST | self::SOUTH => '┬',
        self::EAST | self::WEST | self::NORTH => '┴',
        self::NORTH | self::EAST | self::SOUTH | self::WEST => '┼',
    ];

    /**
     * @var array<int, array<int, string>> What is written, by line and column; a wide character leaves the column after it empty
     */
    private array $text = [];

    /**
     * @var array<int, array<int, array<int, int>>> How each cell is joined, by line, column and arrow
     */
    private array $strokes = [];

    /**
     * How many arrows have been given a mark of their own.
     */
    private int $arrows = 0;

    /**
     * Returns a mark no other arrow on the canvas has, for the strokes of one arrow.
     *
     * @example Every arrow is told apart from the others
     *     $canvas = new \App\Reporter\Diagram\DiagramCanvas();
     *     $canvas->arrow() === $canvas->arrow() // => false
     *
     * @return int The mark
     */
    public function arrow(): int
    {
        return ++$this->arrows;
    }

    /**
     * Writes text, one character to a column and two for a wide one.
     *
     * @param int    $row    The line
     * @param int    $column The column the first character goes in
     * @param string $text   The text
     *
     * @example Text is written where it is put
     *     $canvas = new \App\Reporter\Diagram\DiagramCanvas();
     *     $canvas->write(0, 2, 'ab');
     *     $canvas->lines() // => ['  ab']
     */
    public function write(int $row, int $column, string $text): void
    {
        $at = $column;
        foreach (mb_str_split($text) as $character) {
            $this->text[$row][$at] = $character;
            $width = max(mb_strwidth($character), 1);
            for ($filler = 1; $filler < $width; ++$filler) {
                $this->text[$row][$at + $filler] = '';
            }
            $at += $width;
        }
    }

    /**
     * Draws a line across, from one column to another.
     *
     * @param int $row   The line
     * @param int $from  The column it starts in
     * @param int $to    The column it ends in, right of where it starts
     * @param int $arrow The arrow it belongs to
     *
     * @example A line across is drawn with dashes
     *     $canvas = new \App\Reporter\Diagram\DiagramCanvas();
     *     $canvas->across(0, 0, 3, $canvas->arrow());
     *     $canvas->lines() // => ['────']
     */
    public function across(int $row, int $from, int $to, int $arrow): void
    {
        for ($column = $from; $column <= $to; ++$column) {
            $this->join($row, $column, $arrow, ($column > $from ? self::WEST : 0) | ($column < $to ? self::EAST : 0));
        }
    }

    /**
     * Draws a line down, from one line to another.
     *
     * @param int $column The column
     * @param int $from   The line it starts on
     * @param int $to     The line it ends on, below where it starts
     * @param int $arrow  The arrow it belongs to
     *
     * @example Where a line down meets one across, the two join
     *     $canvas = new \App\Reporter\Diagram\DiagramCanvas();
     *     $arrow = $canvas->arrow();
     *     $canvas->write(0, 0, 'a');
     *     $canvas->down(1, 0, 1, $arrow);
     *     $canvas->across(1, 1, 3, $arrow);
     *     $canvas->lines() // => ['a│', ' └──']
     */
    public function down(int $column, int $from, int $to, int $arrow): void
    {
        for ($row = $from; $row <= $to; ++$row) {
            $this->join($row, $column, $arrow, ($row > $from ? self::NORTH : 0) | ($row < $to ? self::SOUTH : 0));
        }
    }

    /**
     * Joins one cell on some of its sides, for one arrow.
     *
     * @param int $row    The line
     * @param int $column The column
     * @param int $arrow  The arrow the join belongs to
     * @param int $sides  The sides it is joined on
     *
     * @example A cell joined above and on the right is a corner
     *     $canvas = new \App\Reporter\Diagram\DiagramCanvas();
     *     $canvas->join(0, 0, $canvas->arrow(), \App\Reporter\Diagram\DiagramCanvas::NORTH | \App\Reporter\Diagram\DiagramCanvas::EAST);
     *     $canvas->lines() // => ['└']
     */
    public function join(int $row, int $column, int $arrow, int $sides): void
    {
        $this->strokes[$row][$column][$arrow] = ($this->strokes[$row][$column][$arrow] ?? 0) | $sides;
    }

    /**
     * Returns the drawing, one string to a line, with lines that hold only vertical lines left out.
     *
     * @example Nothing drawn is no lines
     *     (new \App\Reporter\Diagram\DiagramCanvas())->lines() // => []
     * @example A line holding nothing but a vertical line is left out
     *     $canvas = new \App\Reporter\Diagram\DiagramCanvas();
     *     $canvas->write(0, 0, 'a');
     *     $canvas->down(1, 0, 2, $canvas->arrow());
     *     $canvas->write(2, 0, 'b');
     *     $canvas->lines() // => ['a│', 'b│']
     *
     * @return list<string> The lines, without trailing spaces
     */
    public function lines(): array
    {
        $rows = array_unique([...array_keys($this->text), ...array_keys($this->strokes)]);
        sort($rows);
        $lines = [];
        foreach ($rows as $row) {
            $cells = $this->cells($row);
            if (!isset($this->text[$row]) && str_replace([' ', '│'], '', implode('', $cells)) === '') {
                continue;
            }
            $lines[] = rtrim(implode('', $cells));
        }

        return $lines;
    }

    /**
     * Returns the characters of one line, column by column.
     *
     * @param int $row The line
     *
     * @example Text wins over a line drawn beneath it
     *     $canvas = new \App\Reporter\Diagram\DiagramCanvas();
     *     $canvas->across(0, 0, 2, $canvas->arrow());
     *     $canvas->write(0, 2, '▶');
     *     $canvas->cells(0) // => ['─', '─', '▶']
     *
     * @return list<string> The characters, an empty string standing where a wide character runs on
     */
    public function cells(int $row): array
    {
        $text = $this->text[$row] ?? [];
        $strokes = $this->strokes[$row] ?? [];
        $width = max([-1, ...array_keys($text), ...array_keys($strokes)]) + 1;
        $cells = [];
        for ($column = 0; $column < $width; ++$column) {
            $cells[] = $text[$column] ?? self::glyph($strokes[$column] ?? []);
        }

        return $cells;
    }

    /**
     * Returns the character for one cell, from the strokes of every arrow through it.
     *
     * @param array<int, int> $strokes The sides the cell is joined on, by arrow
     *
     * @example Strokes of one arrow join
     *     \App\Reporter\Diagram\DiagramCanvas::glyph([1 => \App\Reporter\Diagram\DiagramCanvas::NORTH | \App\Reporter\Diagram\DiagramCanvas::SOUTH | \App\Reporter\Diagram\DiagramCanvas::EAST]) // => '├'
     * @example Two arrows passing straight through one another cross without joining
     *     \App\Reporter\Diagram\DiagramCanvas::glyph([1 => \App\Reporter\Diagram\DiagramCanvas::NORTH | \App\Reporter\Diagram\DiagramCanvas::SOUTH, 2 => \App\Reporter\Diagram\DiagramCanvas::EAST | \App\Reporter\Diagram\DiagramCanvas::WEST]) // => '│'
     *
     * @return string The character
     */
    public static function glyph(array $strokes): string
    {
        $sides = 0;
        $through = false;
        $across = false;
        foreach ($strokes as $stroke) {
            $sides |= $stroke;
            $through = $through || $stroke === (self::NORTH | self::SOUTH);
            $across = $across || $stroke === (self::EAST | self::WEST);
        }

        return $through && $across ? self::GLYPHS[self::NORTH | self::SOUTH] : self::GLYPHS[$sides];
    }
}
