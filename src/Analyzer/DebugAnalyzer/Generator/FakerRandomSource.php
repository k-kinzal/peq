<?php

declare(strict_types=1);

namespace App\Analyzer\DebugAnalyzer\Generator;

use Faker\Generator;

/**
 * The random source peq draws generated graphs from: Faker.
 *
 * A seeded Faker replays the same draws on the PHP runtime it was seeded on. It seeds
 * PHP's Mersenne Twister in its legacy mode below PHP 8.3 and in the standard mode
 * from 8.3 on, so one seed replays one graph per side of that line rather than one
 * graph everywhere. The Mersenne Twister is process-wide, so seeding another Faker
 * moves the draws of this one too: a graph is drawn from one source at a time.
 *
 * @visibility parent
 */
final class FakerRandomSource implements RandomSource
{
    /**
     * @param Generator $faker The Faker generator, seeded when the draws are to be replayed
     */
    public function __construct(
        private readonly Generator $faker,
    ) {}

    /**
     * Draws a number between two bounds, both included.
     *
     * @param int $min The smallest number that may be drawn
     * @param int $max The largest number that may be drawn
     *
     * @return int The drawn number
     */
    public function numberBetween(int $min, int $max): int
    {
        return $this->faker->numberBetween($min, $max);
    }

    /**
     * Draws a boolean, true and false being equally likely.
     *
     * @return bool The drawn boolean
     */
    public function boolean(): bool
    {
        return $this->faker->boolean();
    }

    /**
     * Draws a lowercase word.
     *
     * @return string The drawn word
     */
    public function word(): string
    {
        return $this->faker->word();
    }
}
