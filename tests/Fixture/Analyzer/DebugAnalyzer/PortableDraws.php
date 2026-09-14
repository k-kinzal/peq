<?php

declare(strict_types=1);

namespace Tests\Fixture\Analyzer\DebugAnalyzer;

use App\Analyzer\DebugAnalyzer\Generator\RandomSource;

/**
 * A random source whose sequence is the same on every PHP runtime.
 *
 * Faker, which peq draws from, seeds PHP's Mersenne Twister in its legacy mode below
 * PHP 8.3 and in the standard mode from 8.3 on. Drawing from a 32-bit xorshift whose
 * arithmetic never leaves a 64-bit integer instead is what lets one seed spell one
 * graph on PHP 8.1 and on PHP 8.5 alike.
 */
final class PortableDraws implements RandomSource
{
    private const WORDS = [
        'alias', 'amet', 'animi', 'aut', 'beatae', 'culpa', 'dolor', 'eius',
        'enim', 'error', 'fugit', 'harum', 'illum', 'ipsum', 'iure', 'magni',
        'minus', 'nemo', 'nihil', 'odio', 'omnis', 'porro', 'quia', 'quod',
        'rerum', 'sequi', 'sint', 'soluta', 'tempora', 'ullam', 'velit', 'vero',
    ];

    private int $state;

    /**
     * @param int $seed The seed the sequence starts from
     */
    public function __construct(int $seed)
    {
        $state = ($seed ^ 0x9E3779B9) & 0xFFFFFFFF;
        $this->state = $state === 0 ? 0x9E3779B9 : $state;
    }

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
        $state = $this->state;
        $state ^= ($state << 13) & 0xFFFFFFFF;
        $state ^= $state >> 17;
        $state ^= ($state << 5) & 0xFFFFFFFF;
        $this->state = $state;

        return $min + $state % ($max - $min + 1);
    }

    /**
     * Draws a boolean, true and false being equally likely.
     *
     * @return bool The drawn boolean
     */
    public function boolean(): bool
    {
        return $this->numberBetween(0, 1) === 1;
    }

    /**
     * Draws a lowercase word.
     *
     * @return string The drawn word
     */
    public function word(): string
    {
        return self::WORDS[$this->numberBetween(0, count(self::WORDS) - 1)];
    }
}
