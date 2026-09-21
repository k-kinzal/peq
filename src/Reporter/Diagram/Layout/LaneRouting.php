<?php

declare(strict_types=1);

namespace App\Reporter\Diagram\Layout;

/**
 * How the arrows between two neighbouring columns get across, without two of them
 * ever reading as one.
 *
 * Lines that meet in a drawing join, and a reader follows a join either way. So the
 * arrows between two columns are routed in two stages, which keeps every join between
 * arrows that belong together:
 *
 * - on the left, a lane for each place with several arrows leaving it, where they fan
 *   out: everything on it leaves that one place;
 * - on the right, a lane for each place several arrows arrive at, where they gather:
 *   everything on it arrives at that one place.
 *
 *         ┌──▶ B ──┐
 *     A ──┤        ├──▶ D
 *         └──▶ C ──┘
 *
 * An arrow from a place that fans out to a place that gathers goes over from one lane
 * to the other on a line of its own: places are only ever drawn on even lines, and
 * each such arrow is given an odd one no other arrow between the two columns uses.
 * An arrow between two places on the same line, neither of which has another arrow on
 * this side, is drawn straight across.
 *
 * What is left to go wrong is the order of the fanning lanes: an arrow entering one on
 * a line another leaves on, further left, would share that stretch of line. That
 * order is avoided above any number of crossings, and where no order avoids it the
 * arrow leaving is sent over on a line of its own instead.
 *
 * @visibility App\Reporter\Diagram
 */
final readonly class LaneRouting
{
    /**
     * What sharing a stretch of line costs, against the one a crossing costs.
     */
    public const int SHARED_LINE = 1000;

    /**
     * @param list<Lane>                  $lanes     The lanes, left to right
     * @param list<array{string, string}> $straight  The arrows drawn straight across, with no lane
     * @param list<array{int, int, int}>  $transfers The arrows that go over from one lane to another: the lane they leave, the lane they join, and the line they cross on
     */
    public function __construct(
        public array $lanes,
        public array $straight,
        public array $transfers = [],
    ) {}

    /**
     * Routes the arrows that leave one column for the next.
     *
     * @param DiagramLayout $layout Where everything goes
     * @param int           $layer  The column the arrows leave
     *
     * @example Two arrows fanning out from one place share its lane
     *     $diagram = new \App\Reporter\Diagram\Diagram();
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('a'));
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('b'));
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('c'));
     *     $diagram->relate(new \App\Reporter\Diagram\DiagramEdge('a', 'b'));
     *     $diagram->relate(new \App\Reporter\Diagram\DiagramEdge('a', 'c'));
     *     $layout = \App\Reporter\Diagram\Layout\LayeredLayout::of($diagram);
     *     count(\App\Reporter\Diagram\Layout\LaneRouting::of($layout, 0)->lanes) // => 1
     * @example An arrow between two places on one line is drawn straight
     *     $diagram = new \App\Reporter\Diagram\Diagram();
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('a'));
     *     $diagram->add(new \App\Reporter\Diagram\DiagramNode('b'));
     *     $diagram->relate(new \App\Reporter\Diagram\DiagramEdge('a', 'b'));
     *     $layout = \App\Reporter\Diagram\Layout\LayeredLayout::of($diagram);
     *     count(\App\Reporter\Diagram\Layout\LaneRouting::of($layout, 0)->straight) // => 1
     *
     * @return self The routes
     */
    public static function of(DiagramLayout $layout, int $layer): self
    {
        $links = $layout->linksFrom($layer);
        $leaving = array_count_values(array_column($links, 0));
        $arriving = array_count_values(array_column($links, 1));
        $beside = self::beside($layout, $layer + 1);
        $sends = [];
        $receives = [];
        $crossings = [];
        $straight = [];
        foreach ($links as [$origin, $target]) {
            $row = $layout->rows[$origin];
            $sends[$origin] ??= ['targets' => [], 'crossings' => []];
            $receives[$target] ??= ['origins' => [], 'crossings' => []];
            if ($arriving[$target] === 1 && $leaving[$origin] === 1 && $row === $layout->rows[$target]) {
                $straight[] = [$origin, $target];
            } elseif ($arriving[$target] === 1) {
                $sends[$origin]['targets'][] = $target;
            } elseif ($leaving[$origin] === 1 && ($beside[$row] ?? $target) === $target) {
                $receives[$target]['origins'][] = $origin;
            } else {
                $crossing = self::spare($row, $layout->rows[$target], $crossings);
                $crossings[] = [$origin, $target, $crossing];
                $sends[$origin]['crossings'][] = $crossing;
                $receives[$target]['crossings'][] = $crossing;
            }
        }

        return self::assemble($layout->rows, $sends, $receives, $crossings, $straight);
    }

    /**
     * Returns what each line of a column holds.
     *
     * @param DiagramLayout $layout Where everything goes
     * @param int           $layer  The column
     *
     * @example A column holds its places on their lines
     *     $layout = new \App\Reporter\Diagram\Layout\DiagramLayout([], [[], ['b', 'c']], ['b' => 0, 'c' => 2], []);
     *     \App\Reporter\Diagram\Layout\LaneRouting::beside($layout, 1) // => [0 => 'b', 2 => 'c']
     *
     * @return array<int, string> What is on each line, by line
     */
    public static function beside(DiagramLayout $layout, int $layer): array
    {
        $beside = [];
        foreach ($layout->layers[$layer] ?? [] as $key) {
            $beside[$layout->rows[$key]] = $key;
        }

        return $beside;
    }

    /**
     * Returns an odd line no other arrow crosses over on, as near the place arrived at as can be.
     *
     * @param int                              $from      The line of the place the arrow leaves
     * @param int                              $to        The line of the place it arrives at
     * @param list<array{string, string, int}> $crossings The arrows already given a line
     *
     * @example The line just above the place arrived at, when coming from above
     *     \App\Reporter\Diagram\Layout\LaneRouting::spare(0, 4, []) // => 3
     * @example The next one along, when that is taken
     *     \App\Reporter\Diagram\Layout\LaneRouting::spare(0, 4, [['a', 'b', 3]]) // => 5
     *
     * @return int The line
     */
    public static function spare(int $from, int $to, array $crossings): int
    {
        $taken = array_column($crossings, 2);
        $toward = $from < $to ? -1 : 1;
        for ($distance = 1;; $distance += 2) {
            foreach ([$to + $toward * $distance, $to - $toward * $distance] as $line) {
                if (!in_array($line, $taken, true)) {
                    return $line;
                }
            }
        }
    }

    /**
     * Makes the lanes out of what each place sends and receives, and puts them in order.
     *
     * The lanes where arrows fan out all go left of the lanes where they gather, so
     * that an arrow going over from one to the other always goes right.
     *
     * @param array<string, int>                                                $rows      The line of every place
     * @param array<string, array{targets: list<string>, crossings: list<int>}> $sends     What each place sends along a lane: the places it arrives at, and the lines it goes over on
     * @param array<string, array{origins: list<string>, crossings: list<int>}> $receives  What each place receives along a lane: the places it comes from, and the lines it comes over on
     * @param list<array{string, string, int}>                                  $crossings The arrows that go over, by the places they join and the line they cross on
     * @param list<array{string, string}>                                       $straight  The arrows drawn straight across
     *
     * @example A place that fans out and one that gathers are joined by a crossing
     *     $routing = \App\Reporter\Diagram\Layout\LaneRouting::assemble(
     *         ['a' => 0, 'b' => 4],
     *         ['a' => ['targets' => [], 'crossings' => [3]]],
     *         ['b' => ['origins' => [], 'crossings' => [3]]],
     *         [['a', 'b', 3]],
     *         [],
     *     );
     *     $routing->transfers // => [[0, 1, 3]]
     *
     * @return self The routes
     */
    public static function assemble(array $rows, array $sends, array $receives, array $crossings, array $straight): self
    {
        $fans = [];
        foreach ($sends as $origin => $sent) {
            if ($sent['targets'] !== [] || $sent['crossings'] !== []) {
                $fans[] = new Lane([$origin], $sent['targets'], [], $sent['crossings']);
            }
        }
        [$fans, $gathers, $crossings] = self::untangle(self::arrange($fans, $rows), $rows, $crossings);
        foreach ($receives as $target => $received) {
            if ($received['origins'] !== [] || $received['crossings'] !== []) {
                $gathers[] = new Lane($received['origins'], [$target], $received['crossings']);
            }
        }
        $lanes = [...$fans, ...self::arrange($gathers, $rows)];

        $transfers = [];
        foreach ($crossings as [, , $line]) {
            $transfers[] = [self::laneWith($lanes, $line, true), self::laneWith($lanes, $line, false), $line];
        }

        return new self($lanes, $straight, $transfers);
    }

    /**
     * Sends an arrow that would share a stretch of line with another over a line of its own.
     *
     * An arrow leaving a fanning lane for the place on some line runs right along that
     * line; if an arrow enters a fanning lane further right on the same line, the two
     * share the stretch between the lanes and read as one. Ordering the lanes avoids
     * that when it can, and cannot when two lanes each leave on the line the other
     * enters on. Such an arrow is sent over on an odd line of its own instead, into a
     * lane of its own right of every fanning lane, which no arrow entering a fanning
     * lane ever reaches.
     *
     * @param list<Lane>                       $fans      The fanning lanes, in order
     * @param array<string, int>               $rows      The line of every place
     * @param list<array{string, string, int}> $crossings The arrows already going over
     *
     * @example An arrow leaving on the line another enters on, further right, goes over instead
     *     $left = new \App\Reporter\Diagram\Layout\Lane(['a'], ['c']);
     *     $right = new \App\Reporter\Diagram\Layout\Lane(['b'], ['d']);
     *     [$fans, $gathers] = \App\Reporter\Diagram\Layout\LaneRouting::untangle([$left, $right], ['a' => 0, 'b' => 2, 'c' => 2, 'd' => 4], []);
     *     $gathers[0]->targets // => ['c']
     *
     * @return array{list<Lane>, list<Lane>, list<array{string, string, int}>} The fanning lanes, the lanes the arrows sent over arrive in, and every arrow going over
     */
    public static function untangle(array $fans, array $rows, array $crossings): array
    {
        $entering = [];
        foreach ($fans as $index => $lane) {
            foreach ($lane->entries($rows) as $row) {
                $entering[$row] = $index;
            }
        }

        $untangled = [];
        $gathers = [];
        foreach ($fans as $index => $lane) {
            $kept = [];
            $departures = $lane->departures;
            foreach ($lane->targets as $target) {
                if (($entering[$rows[$target]] ?? -1) <= $index) {
                    $kept[] = $target;

                    continue;
                }
                $line = self::spare($rows[$lane->origins[0]], $rows[$target], $crossings);
                $crossings[] = [$lane->origins[0], $target, $line];
                $departures[] = $line;
                $gathers[] = new Lane([], [$target], [$line]);
            }
            $untangled[] = new Lane($lane->origins, $kept, $lane->arrivals, $departures);
        }

        return [$untangled, $gathers, $crossings];
    }

    /**
     * Returns the lane an arrow going over on a line leaves, or the one it arrives in.
     *
     * Every arrow going over between two columns has a line of its own, so the line is
     * enough to find both of its lanes.
     *
     * @param list<Lane> $lanes   The lanes, left to right
     * @param int        $line    The line the arrow goes over on
     * @param bool       $leaving Whether to find the lane it leaves rather than the one it arrives in
     *
     * @example The lane an arrow leaves is the one it departs on that line from
     *     $lanes = [new \App\Reporter\Diagram\Layout\Lane(['a'], [], [], [3]), new \App\Reporter\Diagram\Layout\Lane([], ['b'], [3])];
     *     \App\Reporter\Diagram\Layout\LaneRouting::laneWith($lanes, 3, true) // => 0
     *
     * @return int Where the lane stands, counting from the left from zero
     */
    public static function laneWith(array $lanes, int $line, bool $leaving): int
    {
        foreach ($lanes as $index => $lane) {
            if (in_array($line, $leaving ? $lane->departures : $lane->arrivals, true)) {
                return $index;
            }
        }

        return 0;
    }

    /**
     * Puts lanes in the order that costs least, by swapping neighbours while a swap helps.
     *
     * What two lanes cost depends only on which of the two is on the left, so each
     * swap that helps lowers the cost of the whole order, and the swapping ends.
     *
     * @param list<Lane>         $lanes The lanes
     * @param array<string, int> $rows  The line of every place
     *
     * @example Two lanes that would cross each other twice are swapped, and cross nothing
     *     $first = new \App\Reporter\Diagram\Layout\Lane(['a'], ['c']);
     *     $second = new \App\Reporter\Diagram\Layout\Lane(['b'], ['d']);
     *     \App\Reporter\Diagram\Layout\LaneRouting::arrange([$first, $second], ['a' => 0, 'b' => 2, 'c' => 4, 'd' => 6]) === [$second, $first] // => true
     *
     * @return list<Lane> The lanes, left to right
     */
    public static function arrange(array $lanes, array $rows): array
    {
        usort($lanes, static fn (Lane $left, Lane $right): int => $left->span($rows) <=> $right->span($rows));
        for ($pass = 0; $pass < count($lanes); ++$pass) {
            $swapped = false;
            for ($index = 0; $index + 1 < count($lanes); ++$index) {
                if (self::cost($lanes[$index + 1], $lanes[$index], $rows) < self::cost($lanes[$index], $lanes[$index + 1], $rows)) {
                    array_splice($lanes, $index, 2, [$lanes[$index + 1], $lanes[$index]]);
                    $swapped = true;
                }
            }
            if (!$swapped) {
                break;
            }
        }

        return $lanes;
    }

    /**
     * Returns what it costs to put one lane left of another.
     *
     * An arrow leaving the left lane for the right crosses the right lane where the
     * right lane runs past that line; an arrow entering the right lane from the left
     * crosses the left lane where the left lane runs past that line. An arrow leaving
     * the left lane on a line an arrow enters the right lane on shares a stretch of
     * line with it, which costs more than any number of crossings.
     *
     * @param Lane               $left  The lane on the left
     * @param Lane               $right The lane on the right
     * @param array<string, int> $rows  The line of every place
     *
     * @example An arrow leaving on the line another enters on shares a stretch of line with it
     *     $left = new \App\Reporter\Diagram\Layout\Lane(['a'], ['c']);
     *     $right = new \App\Reporter\Diagram\Layout\Lane(['b'], ['d']);
     *     \App\Reporter\Diagram\Layout\LaneRouting::cost($left, $right, ['a' => 0, 'b' => 2, 'c' => 2, 'd' => 4]) // => 1000
     * @example Lanes that never meet cost nothing
     *     $left = new \App\Reporter\Diagram\Layout\Lane(['a'], ['c']);
     *     $right = new \App\Reporter\Diagram\Layout\Lane(['b'], ['d']);
     *     \App\Reporter\Diagram\Layout\LaneRouting::cost($left, $right, ['a' => 0, 'b' => 4, 'c' => 2, 'd' => 6]) // => 0
     *
     * @return int The cost
     */
    public static function cost(Lane $left, Lane $right, array $rows): int
    {
        [$leftTop, $leftBottom] = $left->span($rows);
        [$rightTop, $rightBottom] = $right->span($rows);
        $leaving = $left->exits($rows);
        $entering = $right->entries($rows);
        $arriving = $right->exits($rows);
        $cost = 0;
        foreach ($leaving as $row) {
            $shared = in_array($row, $entering, true);
            $crossed = !$shared && !in_array($row, $arriving, true) && $row > $rightTop && $row < $rightBottom;
            $cost += ($shared ? self::SHARED_LINE : 0) + ($crossed ? 1 : 0);
        }
        foreach ($entering as $row) {
            $cost += $row > $leftTop && $row < $leftBottom && !in_array($row, $leaving, true) ? 1 : 0;
        }

        return $cost;
    }
}
