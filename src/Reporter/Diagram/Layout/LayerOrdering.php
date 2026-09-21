<?php

declare(strict_types=1);

namespace App\Reporter\Diagram\Layout;

/**
 * Puts the places in each column of a drawing in the order that crosses fewest arrows.
 *
 * Finding the order with the fewest crossings is NP-hard even for two columns, so this
 * uses the barycentre heuristic of Sugiyama, Tagawa and Toda: every place moves
 * towards the average position of what it is joined to in the column beside it,
 * sweeping right and then left, and the best order any sweep reached is kept.
 *
 * @visibility App\Reporter\Diagram
 */
final class LayerOrdering
{
    /**
     * How many times the columns are swept, each time right and then left.
     */
    public const SWEEPS = 4;

    /**
     * Orders each column so that as few arrows as can be found cross.
     *
     * @param list<list<string>>          $layers The places in each column
     * @param list<array{string, string}> $links  The arrows between neighbouring columns
     *
     * @example Two arrows that would cross are uncrossed
     *     \App\Reporter\Diagram\Layout\LayerOrdering::order([['a', 'b'], ['c', 'd']], [['a', 'd'], ['b', 'c']]) // => [['a', 'b'], ['d', 'c']]
     *
     * @return list<list<string>> The places in each column, in order
     */
    public static function order(array $layers, array $links): array
    {
        [$before, $after] = self::neighbours($links);
        $best = $layers;
        $fewest = self::crossings($layers, $links);
        for ($sweep = 0; $sweep < self::SWEEPS; ++$sweep) {
            for ($layer = 1; $layer < count($layers); ++$layer) {
                $layers[$layer] = self::reorder($layers[$layer], $layers[$layer - 1], $before);
            }
            for ($layer = count($layers) - 2; $layer >= 0; --$layer) {
                $layers[$layer] = self::reorder($layers[$layer], $layers[$layer + 1], $after);
            }
            $crossings = self::crossings($layers, $links);
            if ($crossings < $fewest) {
                $best = $layers;
                $fewest = $crossings;
            }
        }

        return $best;
    }

    /**
     * Returns what each place is joined to, on its left and on its right.
     *
     * @param list<array{string, string}> $links The arrows
     *
     * @example An arrow joins its two ends each way
     *     \App\Reporter\Diagram\Layout\LayerOrdering::neighbours([['a', 'b']]) // => [['b' => ['a']], ['a' => ['b']]]
     *
     * @return array{array<string, list<string>>, array<string, list<string>>} What each place is joined to on its left, and on its right
     */
    public static function neighbours(array $links): array
    {
        $before = [];
        $after = [];
        foreach ($links as [$origin, $target]) {
            $before[$target][] = $origin;
            $after[$origin][] = $target;
        }

        return [$before, $after];
    }

    /**
     * Orders one column by the average position of what each place is joined to.
     *
     * A place joined to nothing in the neighbouring column keeps its position.
     *
     * @param list<string>                $layer      The column to order
     * @param list<string>                $fixed      The neighbouring column, already in order
     * @param array<string, list<string>> $neighbours What each place is joined to
     *
     * @example Places follow what they are joined to
     *     \App\Reporter\Diagram\Layout\LayerOrdering::reorder(['c', 'd'], ['a', 'b'], ['c' => ['b'], 'd' => ['a']]) // => ['d', 'c']
     *
     * @return list<string> The column, in order
     */
    public static function reorder(array $layer, array $fixed, array $neighbours): array
    {
        $position = array_flip($fixed);
        $weighed = [];
        foreach ($layer as $index => $key) {
            $places = [];
            foreach ($neighbours[$key] ?? [] as $neighbour) {
                if (isset($position[$neighbour])) {
                    $places[] = $position[$neighbour];
                }
            }
            $weighed[] = [$places === [] ? (float) $index : array_sum($places) / count($places), $index, $key];
        }
        usort($weighed, static fn (array $left, array $right): int => [$left[0], $left[1]] <=> [$right[0], $right[1]]);

        return array_column($weighed, 2);
    }

    /**
     * Counts the arrows that cross, between every pair of neighbouring columns.
     *
     * @param list<list<string>>          $layers The places in each column, in order
     * @param list<array{string, string}> $links  The arrows
     *
     * @example Two arrows to swapped places cross once
     *     \App\Reporter\Diagram\Layout\LayerOrdering::crossings([['a', 'b'], ['c', 'd']], [['a', 'd'], ['b', 'c']]) // => 1
     *
     * @return int How many pairs of arrows cross
     */
    public static function crossings(array $layers, array $links): int
    {
        $position = [];
        $column = [];
        foreach ($layers as $layer => $keys) {
            foreach ($keys as $index => $key) {
                $position[$key] = $index;
                $column[$key] = $layer;
            }
        }

        $crossings = 0;
        foreach ($links as $index => [$origin, $target]) {
            foreach (array_slice($links, $index + 1) as [$otherOrigin, $otherTarget]) {
                $sameGap = $column[$origin] === $column[$otherOrigin];
                $opposed = ($position[$origin] - $position[$otherOrigin]) * ($position[$target] - $position[$otherTarget]) < 0;
                $crossings += $sameGap && $opposed ? 1 : 0;
            }
        }

        return $crossings;
    }
}
