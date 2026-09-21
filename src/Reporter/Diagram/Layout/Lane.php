<?php

declare(strict_types=1);

namespace App\Reporter\Diagram\Layout;

/**
 * A vertical line between two columns that arrows share on their way across.
 *
 * Arrows enter a lane from the left and leave it to the right. They enter on the line
 * of the place they leave, or on a line of their own where they come over from another
 * lane; they leave on the line of the place they arrive at, or on a line of their own
 * where they go over to another lane.
 *
 * @visibility App\Reporter\Diagram
 */
final class Lane
{
    /**
     * @param list<string> $origins    The places whose arrows enter the lane on their own line
     * @param list<string> $targets    The places the lane's arrows arrive at, on their own line
     * @param list<int>    $arrivals   The lines arrows come over from another lane on
     * @param list<int>    $departures The lines arrows go over to another lane on
     */
    public function __construct(
        public readonly array $origins,
        public readonly array $targets,
        public readonly array $arrivals = [],
        public readonly array $departures = [],
    ) {
        assert($this->origins !== [] || $this->arrivals !== [], 'Something enters a lane');
        assert($this->targets !== [] || $this->departures !== [], 'Something leaves a lane');
    }

    /**
     * Returns the lines a lane runs between.
     *
     * @param array<string, int> $rows The line of every place
     *
     * @example A lane runs from its highest end to its lowest
     *     (new \App\Reporter\Diagram\Layout\Lane(['a'], ['b', 'c']))->span(['a' => 2, 'b' => 0, 'c' => 4]) // => [0, 4]
     *
     * @return array{int, int} The first line and the last
     */
    public function span(array $rows): array
    {
        $lines = [...$this->entries($rows), ...$this->exits($rows)];

        return $lines === [] ? [0, 0] : [min($lines), max($lines)];
    }

    /**
     * Returns the lines arrows enter the lane on.
     *
     * @param array<string, int> $rows The line of every place
     *
     * @example Arrows enter on the lines of the places they leave, and where they come over
     *     (new \App\Reporter\Diagram\Layout\Lane(['a'], ['c'], [3]))->entries(['a' => 0, 'c' => 4]) // => [0, 3]
     *
     * @return list<int> The lines
     */
    public function entries(array $rows): array
    {
        return [...array_map(static fn (string $key): int => $rows[$key], $this->origins), ...$this->arrivals];
    }

    /**
     * Returns the lines arrows leave the lane on.
     *
     * @param array<string, int> $rows The line of every place
     *
     * @example Arrows leave on the lines of the places they arrive at, and where they go over
     *     (new \App\Reporter\Diagram\Layout\Lane(['a'], ['b'], [], [3]))->exits(['a' => 1, 'b' => 0]) // => [0, 3]
     *
     * @return list<int> The lines
     */
    public function exits(array $rows): array
    {
        return [...array_map(static fn (string $key): int => $rows[$key], $this->targets), ...$this->departures];
    }
}
