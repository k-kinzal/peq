<?php

declare(strict_types=1);

namespace App\Reporter\Diagram;

use App\Reporter\Diagram\Layout\DiagramLayout;
use App\Reporter\Diagram\Layout\LaneRouting;
use App\Reporter\Diagram\Layout\LayeredLayout;
use App\Reporter\Diagram\Layout\LayoutItem;
use App\Reporter\Diagram\Layout\LayoutItemKind;
use Override;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Draws a graph in the terminal, as symbols joined by arrows, left to right.
 *
 *                           ┌──▶ App\Http\Controller::show ───┐
 *     App\Http\Controller ──┤                                 ├──▶ App\Domain\Invoice::total
 *                           └──▶ App\Http\Controller::store ──┘
 *
 * Every symbol is drawn once, in the column of its level, and a symbol several arrows
 * arrive at gathers them into one. An arrow that cannot be drawn from one column to
 * the next — to a symbol in the same column, or further left — points instead at the
 * symbol's name marked the way the tree marks it: `(recursive)` when the arrow closes
 * a cycle, `(*)` when the symbol is simply drawn elsewhere.
 *
 * A drawing is as wide as the terminal it is written to, and as long as it needs to
 * be. When its columns do not fit, it is cut into bands, one below the other, and the
 * column a band ends with is the column the next one starts with, so a symbol marked
 * `…` where one band ends is where the next one carries on from.
 *
 * @visibility App\Reporter
 */
final class TerminalRenderer implements DiagramRenderer
{
    /**
     * How wide a drawing is when nothing says how wide the terminal is.
     */
    public const DEFAULT_WIDTH = 80;

    /**
     * What a symbol pointed at again is marked with, as the tree marks it.
     */
    public const REFERENCE = ' (*)';

    /**
     * What a symbol pointed at again by an arrow that closes a cycle is marked with, as the tree marks it.
     */
    public const RECURSION = ' (recursive)';

    /**
     * What marks a drawing going on in the next band.
     */
    public const CONTINUED = '…';

    /**
     * What an arrow is drawn arriving with.
     */
    public const ARROWHEAD = '▶';

    /**
     * @param int $width How many columns the drawing may take
     */
    public function __construct(
        private readonly int $width = self::DEFAULT_WIDTH,
    ) {}

    /**
     * Writes a drawing, or nothing when it holds nothing.
     *
     * @param Diagram         $diagram The symbols and relations to draw
     * @param OutputInterface $output  Where the drawing is written
     */
    #[Override]
    public function render(Diagram $diagram, OutputInterface $output): void
    {
        foreach ($this->draw($diagram) as $line) {
            $output->writeln($line, OutputInterface::OUTPUT_RAW);
        }
    }

    /**
     * Returns the drawing, one string to a line.
     *
     * @param Diagram $diagram The symbols and relations to draw
     *
     * @example A symbol pointing at another is joined to it by an arrow
     *     $diagram = new \App\Reporter\Diagram\Diagram();
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('A'));
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('B'));
     *     $diagram->relate(new \App\Reporter\Diagram\DiagramEdge('A', 'B'));
     *     (new \App\Reporter\Diagram\TerminalRenderer())->draw($diagram) // => ['A ──▶ B']
     * @example A drawing that holds nothing is no lines
     *     (new \App\Reporter\Diagram\TerminalRenderer())->draw(new \App\Reporter\Diagram\Diagram()) // => []
     *
     * @return list<string> The lines
     */
    public function draw(Diagram $diagram): array
    {
        if ($diagram->empty()) {
            return [];
        }

        $layout = LayeredLayout::of($diagram);
        $routes = [];
        for ($layer = 0; $layer + 1 < count($layout->layers); ++$layer) {
            $routes[] = LaneRouting::of($layout, $layer);
        }

        $lines = [];
        foreach ($this->bands($layout, $routes) as $index => [$first, $last]) {
            if ($index > 0) {
                $lines[] = '';
            }
            array_push($lines, ...$this->band($layout, $routes, $first, $last));
        }

        return $lines;
    }

    /**
     * Cuts the columns into bands that each fit the width, the last column of one being the first of the next.
     *
     * A band holds at least two columns, and so at least one set of arrows, even when
     * that is wider than the width: a band of one column would draw no arrow at all.
     *
     * @param DiagramLayout     $layout Where everything goes
     * @param list<LaneRouting> $routes How the arrows leaving each column get across
     *
     * @example A drawing that fits is one band
     *     $diagram = new \App\Reporter\Diagram\Diagram();
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('A'));
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('B'));
     *     $diagram->relate(new \App\Reporter\Diagram\DiagramEdge('A', 'B'));
     *     $layout = \App\Reporter\Diagram\Layout\LayeredLayout::of($diagram);
     *     (new \App\Reporter\Diagram\TerminalRenderer())->bands($layout, [\App\Reporter\Diagram\Layout\LaneRouting::of($layout, 0)]) // => [[0, 1]]
     *
     * @return list<array{int, int}> The first and last column of each band
     */
    public function bands(DiagramLayout $layout, array $routes): array
    {
        $last = count($layout->layers) - 1;
        $bands = [];
        $first = 0;
        do {
            $end = min($first + 1, $last);
            while ($end < $last && $this->reach($layout, $routes, $first, $end + 1) <= $this->width) {
                ++$end;
            }
            $bands[] = [$first, $end];
            $first = $end;
        } while ($end < $last);

        return $bands;
    }

    /**
     * Returns how many columns of characters a band takes.
     *
     * @param DiagramLayout     $layout Where everything goes
     * @param list<LaneRouting> $routes How the arrows leaving each column get across
     * @param int               $first  The first column of the band
     * @param int               $last   The last column of the band
     *
     * @example A band is as wide as its names and the arrow between them
     *     $diagram = new \App\Reporter\Diagram\Diagram();
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('A'));
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('B'));
     *     $diagram->relate(new \App\Reporter\Diagram\DiagramEdge('A', 'B'));
     *     $layout = \App\Reporter\Diagram\Layout\LayeredLayout::of($diagram);
     *     (new \App\Reporter\Diagram\TerminalRenderer())->reach($layout, [\App\Reporter\Diagram\Layout\LaneRouting::of($layout, 0)], 0, 1) // => 7
     *
     * @return int The width
     */
    public function reach(DiagramLayout $layout, array $routes, int $first, int $last): int
    {
        $labels = $this->labels($layout, $first, $last);

        return $this->columns($layout, $routes, $labels, $first, $last)[$last] + self::widthOf($layout, $labels, $last);
    }

    /**
     * Returns what each place in a band is written as.
     *
     * @param DiagramLayout $layout Where everything goes
     * @param int           $first  The first column of the band
     * @param int           $last   The last column of the band
     *
     * @example A symbol is written as its name
     *     $diagram = new \App\Reporter\Diagram\Diagram();
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('A'));
     *     $layout = \App\Reporter\Diagram\Layout\LayeredLayout::of($diagram);
     *     array_values((new \App\Reporter\Diagram\TerminalRenderer())->labels($layout, 0, 0)) // => ['A']
     *
     * @return array<string, string> What each place is written as, by what identifies it
     */
    public function labels(DiagramLayout $layout, int $first, int $last): array
    {
        $labels = [];
        for ($layer = $first; $layer <= $last; ++$layer) {
            foreach ($layout->layers[$layer] as $key) {
                if ($layer > 0 && $layer === $first && !$layout->leaves($key)) {
                    continue;
                }
                $labels[$key] = self::label($layout, $layout->items[$key], $first, $last);
            }
        }

        return $labels;
    }

    /**
     * Returns what one place is written as, in a band.
     *
     * @param DiagramLayout $layout Where everything goes
     * @param LayoutItem    $item   The place
     * @param int           $first  The first column of the band
     * @param int           $last   The last column of the band
     *
     * @example A symbol pointed at again is marked as the tree marks it
     *     $layout = new \App\Reporter\Diagram\Layout\DiagramLayout([], [[], []], [], []);
     *     \App\Reporter\Diagram\TerminalRenderer::label($layout, \App\Reporter\Diagram\Layout\LayoutItem::reference('B', 'A', 1, false), 0, 1) // => 'A (*)'
     * @example And so is one an arrow closing a cycle points at
     *     $layout = new \App\Reporter\Diagram\Layout\DiagramLayout([], [[], []], [], []);
     *     \App\Reporter\Diagram\TerminalRenderer::label($layout, \App\Reporter\Diagram\Layout\LayoutItem::reference('B', 'A', 1, true), 0, 1) // => 'A (recursive)'
     * @example A symbol that goes on in the next band is marked where this one ends
     *     $a = \App\Reporter\Diagram\Layout\LayoutItem::symbol('A', 1);
     *     $b = \App\Reporter\Diagram\Layout\LayoutItem::symbol('B', 2);
     *     $layout = new \App\Reporter\Diagram\Layout\DiagramLayout([$a->key => $a, $b->key => $b], [[], [$a->key], [$b->key]], [], [[$a->key, $b->key]]);
     *     \App\Reporter\Diagram\TerminalRenderer::label($layout, $a, 0, 1) // => 'A …'
     *
     * @return string What the place is written as
     */
    public static function label(DiagramLayout $layout, LayoutItem $item, int $first, int $last): string
    {
        $closes = $item->layer === $last && $last > $first && $last < count($layout->layers) - 1;

        return $item->subject.match ($item->kind) {
            LayoutItemKind::Symbol => $closes && $layout->leaves($item->key) ? ' '.self::CONTINUED : '',
            LayoutItemKind::Reference => self::REFERENCE,
            LayoutItemKind::Recursion => self::RECURSION,
        };
    }

    /**
     * Returns the character column each column of places in a band starts at.
     *
     * Between two columns go a short arrow, the lanes two characters apart, and the
     * arrowhead; with no lanes, the arrow alone.
     *
     * @param DiagramLayout         $layout Where everything goes
     * @param list<LaneRouting>     $routes How the arrows leaving each column get across
     * @param array<string, string> $labels What each place in the band is written as
     * @param int                   $first  The first column of the band
     * @param int                   $last   The last column of the band
     *
     * @example A column with no lanes after it is followed by a short arrow
     *     $diagram = new \App\Reporter\Diagram\Diagram();
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('A'));
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('B'));
     *     $diagram->relate(new \App\Reporter\Diagram\DiagramEdge('A', 'B'));
     *     $layout = \App\Reporter\Diagram\Layout\LayeredLayout::of($diagram);
     *     $renderer = new \App\Reporter\Diagram\TerminalRenderer();
     *     $renderer->columns($layout, [\App\Reporter\Diagram\Layout\LaneRouting::of($layout, 0)], $renderer->labels($layout, 0, 1), 0, 1) // => [0 => 0, 1 => 6]
     *
     * @return array<int, int> Where each column starts
     */
    public function columns(DiagramLayout $layout, array $routes, array $labels, int $first, int $last): array
    {
        $starts = [$first => 0];
        for ($layer = $first; $layer < $last; ++$layer) {
            $lanes = count($routes[$layer]->lanes);
            $starts[$layer + 1] = $starts[$layer] + self::widthOf($layout, $labels, $layer) + ($lanes === 0 ? 5 : 2 * $lanes + 6);
        }

        return $starts;
    }

    /**
     * Returns how wide a column of places is written, the widest of what it holds.
     *
     * @param DiagramLayout         $layout Where everything goes
     * @param array<string, string> $labels What each place is written as
     * @param int                   $layer  The column
     *
     * @example A column is as wide as its widest name
     *     $layout = new \App\Reporter\Diagram\Layout\DiagramLayout([], [['a', 'b']], [], []);
     *     \App\Reporter\Diagram\TerminalRenderer::widthOf($layout, ['a' => 'App', 'b' => 'App\\Invoice'], 0) // => 11
     *
     * @return int The width
     */
    public static function widthOf(DiagramLayout $layout, array $labels, int $layer): int
    {
        $width = 0;
        foreach ($layout->layers[$layer] as $key) {
            $width = max($width, mb_strwidth($labels[$key] ?? ''));
        }

        return $width;
    }

    /**
     * Draws one band.
     *
     * @param DiagramLayout     $layout Where everything goes
     * @param list<LaneRouting> $routes How the arrows leaving each column get across
     * @param int               $first  The first column of the band
     * @param int               $last   The last column of the band
     *
     * @example A symbol fanning out to two is drawn between them
     *     $diagram = new \App\Reporter\Diagram\Diagram();
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('A'));
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('B'));
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('C'));
     *     $diagram->relate(new \App\Reporter\Diagram\DiagramEdge('A', 'B'));
     *     $diagram->relate(new \App\Reporter\Diagram\DiagramEdge('A', 'C'));
     *     $layout = \App\Reporter\Diagram\Layout\LayeredLayout::of($diagram);
     *     (new \App\Reporter\Diagram\TerminalRenderer())->band($layout, [\App\Reporter\Diagram\Layout\LaneRouting::of($layout, 0)], 0, 1) // => ['    ┌──▶ B', 'A ──┤', '    └──▶ C']
     *
     * @return list<string> The lines of the band
     */
    public function band(DiagramLayout $layout, array $routes, int $first, int $last): array
    {
        $labels = $this->labels($layout, $first, $last);
        $starts = $this->columns($layout, $routes, $labels, $first, $last);
        $canvas = new DiagramCanvas();
        foreach ($labels as $key => $label) {
            $canvas->write($layout->rows[$key], $starts[$layout->items[$key]->layer], $label);
        }
        for ($layer = $first; $layer < $last; ++$layer) {
            $end = $starts[$layer] + self::widthOf($layout, $labels, $layer);
            self::connect($canvas, $layout, $routes[$layer], $labels, $starts[$layer], $end, $starts[$layer + 1]);
        }

        return $canvas->lines();
    }

    /**
     * Draws the arrows between one column and the next.
     *
     * @param DiagramCanvas         $canvas  Where they are drawn
     * @param DiagramLayout         $layout  Where everything goes
     * @param LaneRouting           $routing How the arrows get across
     * @param array<string, string> $labels  What each place is written as
     * @param int                   $start   The character column the places they leave start at
     * @param int                   $end     The character column those places end at
     * @param int                   $next    The character column the places they arrive at start at
     *
     * @example A straight arrow runs from after the name to just before the next
     *     $diagram = new \App\Reporter\Diagram\Diagram();
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('A'));
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('B'));
     *     $diagram->relate(new \App\Reporter\Diagram\DiagramEdge('A', 'B'));
     *     $layout = \App\Reporter\Diagram\Layout\LayeredLayout::of($diagram);
     *     $canvas = new \App\Reporter\Diagram\DiagramCanvas();
     *     \App\Reporter\Diagram\TerminalRenderer::connect($canvas, $layout, \App\Reporter\Diagram\Layout\LaneRouting::of($layout, 0), array_fill_keys(array_keys($layout->items), 'A'), 0, 1, 6);
     *     $canvas->lines() // => ['  ──▶']
     */
    public static function connect(DiagramCanvas $canvas, DiagramLayout $layout, LaneRouting $routing, array $labels, int $start, int $end, int $next): void
    {
        foreach ($routing->lanes as $index => $lane) {
            $column = $end + 3 + 2 * $index;
            $arrow = $canvas->arrow();
            [$top, $bottom] = $lane->span($layout->rows);
            $canvas->down($column, $top, $bottom, $arrow);
            foreach ($lane->origins as $origin) {
                $canvas->across($layout->rows[$origin], self::leave($labels[$origin] ?? '', $start), $column, $arrow);
            }
            foreach ($lane->targets as $target) {
                self::arrive($canvas, $layout, $target, $column, $next, $arrow);
            }
        }
        foreach ($routing->transfers as [$from, $to, $line]) {
            $canvas->across($line, $end + 3 + 2 * $from, $end + 3 + 2 * $to, $canvas->arrow());
        }
        foreach ($routing->straight as [$origin, $target]) {
            self::arrive($canvas, $layout, $target, self::leave($labels[$origin] ?? '', $start), $next, $canvas->arrow());
        }
    }

    /**
     * Returns the character column an arrow leaving a place starts at, a space after its name.
     *
     * @param string $label What the place is written as
     * @param int    $start The character column its column starts at
     *
     * @example An arrow leaves a name a space after it
     *     \App\Reporter\Diagram\TerminalRenderer::leave('App', 4) // => 8
     *
     * @return int The character column
     */
    public static function leave(string $label, int $start): int
    {
        return $start + mb_strwidth($label) + 1;
    }

    /**
     * Draws the last stretch of an arrow, into the place it arrives at, with an arrowhead and a space before the name.
     *
     * @param DiagramCanvas $canvas Where it is drawn
     * @param DiagramLayout $layout Where everything goes
     * @param string        $target What identifies the place it arrives at
     * @param int           $from   The character column the stretch starts at
     * @param int           $next   The character column the place it arrives at starts at
     * @param int           $arrow  The arrow the stretch belongs to
     *
     * @example An arrow arrives at a symbol with an arrowhead
     *     $diagram = new \App\Reporter\Diagram\Diagram();
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('A'));
     *     $layout = \App\Reporter\Diagram\Layout\LayeredLayout::of($diagram);
     *     $canvas = new \App\Reporter\Diagram\DiagramCanvas();
     *     \App\Reporter\Diagram\TerminalRenderer::arrive($canvas, $layout, \App\Reporter\Diagram\Layout\LayoutItem::keyOf('A'), 0, 5, $canvas->arrow());
     *     $canvas->lines() // => ['───▶']
     */
    public static function arrive(DiagramCanvas $canvas, DiagramLayout $layout, string $target, int $from, int $next, int $arrow): void
    {
        $row = $layout->rows[$target];
        $canvas->across($row, $from, $next - 3, $arrow);
        $canvas->write($row, $next - 2, self::ARROWHEAD);
    }
}
