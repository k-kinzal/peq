<?php

declare(strict_types=1);

namespace App\Reporter\Diagram\Layout;

/**
 * Gives each place in a drawing the line it is drawn on.
 *
 * A place goes near the middle of what it is joined to, so that an arrow between two
 * places is drawn straight wherever it can be, and a symbol with two arrows leaving it
 * sits between the two symbols they arrive at. Places in one column keep their order
 * and stay two lines apart, which leaves a line between two neighbours for the arrows
 * that fan out to them.
 *
 * @visibility App\Reporter\Diagram
 */
final class RowPlacement
{
    /**
     * How many lines apart two places in one column are, at the least.
     */
    public const int SPACING = 2;

    /**
     * Gives each place a line, near the middle of what it is joined to.
     *
     * @param list<list<string>>          $layers The places in each column, in order
     * @param list<array{string, string}> $links  The arrows
     *
     * @example A symbol with two arrows leaving it sits between the two it points at
     *     \App\Reporter\Diagram\Layout\RowPlacement::rows([['a'], ['b', 'c']], [['a', 'b'], ['a', 'c']]) // => ['a' => 1, 'b' => 0, 'c' => 2]
     *
     * @return array<string, int> The line of each place, the first being line zero
     */
    public static function rows(array $layers, array $links): array
    {
        [$before, $after] = LayerOrdering::neighbours($links);
        $rows = [];
        foreach ($layers as $layer) {
            foreach ($layer as $index => $key) {
                $rows[$key] = $index * self::SPACING;
            }
        }
        for ($sweep = 0; $sweep < LayerOrdering::SWEEPS; ++$sweep) {
            for ($layer = 1; $layer < count($layers); ++$layer) {
                $rows = array_merge($rows, self::place($layers[$layer], $before, $rows));
            }
            for ($layer = count($layers) - 2; $layer >= 0; --$layer) {
                $rows = array_merge($rows, self::place($layers[$layer], $after, $rows));
            }
        }
        $top = $rows === [] ? 0 : min($rows);

        return array_map(static fn (int $row): int => $row - $top, $rows);
    }

    /**
     * Gives the places of one column the lines nearest the middle of what each is joined to.
     *
     * @param list<string>                $layer      The column, in order
     * @param array<string, list<string>> $neighbours What each place is joined to in the neighbouring column
     * @param array<string, int>          $rows       The lines every place has now
     *
     * @example Two places that want the same line are moved apart around it
     *     \App\Reporter\Diagram\Layout\RowPlacement::place(['b', 'c'], ['b' => ['a'], 'c' => ['a']], ['a' => 4, 'b' => 0, 'c' => 2]) // => ['b' => 3, 'c' => 5]
     *
     * @return array<string, int> The line of each place in the column
     */
    public static function place(array $layer, array $neighbours, array $rows): array
    {
        $wanted = [];
        foreach ($layer as $key) {
            $around = array_map(static fn (string $neighbour): int => $rows[$neighbour], $neighbours[$key] ?? []);
            $wanted[] = $around === [] ? (float) $rows[$key] : self::median($around);
        }

        return array_combine($layer, self::fit($wanted, self::SPACING));
    }

    /**
     * Returns the lines nearest to the ones wanted, in order and a given distance apart.
     *
     * This is isotonic regression, solved by pooling adjacent violators: a run of
     * places that all want to be above one another is moved as a block to the mean of
     * what they want, which is the placement nearest to what every one of them wanted.
     *
     * @param list<float> $wanted  The line each place wants, in the order they must keep
     * @param int         $spacing How many lines apart two neighbours are, at the least
     *
     * @example Places that want the same line share the difference
     *     \App\Reporter\Diagram\Layout\RowPlacement::fit([1.0, 1.0], 2) // => [0, 2]
     * @example Places already far enough apart get what they want
     *     \App\Reporter\Diagram\Layout\RowPlacement::fit([0.0, 5.0], 2) // => [0, 5]
     *
     * @return list<int> The lines
     */
    public static function fit(array $wanted, int $spacing): array
    {
        $blocks = [];
        foreach ($wanted as $index => $value) {
            $block = [$value - $spacing * $index, 1];
            while ($blocks !== [] && $blocks[count($blocks) - 1][0] / $blocks[count($blocks) - 1][1] > $block[0] / $block[1]) {
                $previous = array_pop($blocks);
                $block = [$previous[0] + $block[0], $previous[1] + $block[1]];
            }
            $blocks[] = $block;
        }

        $rows = [];
        foreach ($blocks as [$sum, $count]) {
            $level = (int) round($sum / $count);
            for ($member = 0; $member < $count; ++$member) {
                $rows[] = $level + $spacing * count($rows);
            }
        }

        return $rows;
    }

    /**
     * Returns the median of some lines, halfway between the middle two when there is an even number.
     *
     * @param non-empty-list<int> $values The lines
     *
     * @example The median of two lines is halfway between them
     *     \App\Reporter\Diagram\Layout\RowPlacement::median([0, 2]) // => 1.0
     * @example The median of three is the middle one
     *     \App\Reporter\Diagram\Layout\RowPlacement::median([9, 0, 2]) // => 2.0
     *
     * @return float The median
     */
    public static function median(array $values): float
    {
        sort($values);
        $middle = intdiv(count($values), 2);

        return count($values) % 2 === 1
            ? (float) $values[$middle]
            : ($values[$middle - 1] + $values[$middle]) / 2;
    }
}
