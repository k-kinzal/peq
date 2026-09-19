<?php

declare(strict_types=1);

namespace App\Analyzer\DebugAnalyzer\Generator;

/**
 * The seeded sequence of draws a generated graph is made of.
 *
 * Every choice the generators make — how many members a class declares, whether
 * a method calls another, which word a name is spelled with — is one of three
 * draws taken from here. The sequence is the source's own state rather than PHP's
 * process-wide random generator: a graph drawn with a seed is only reproducible
 * while nothing else touches the stream it is drawn from, and a shared generator
 * is reseeded by whatever else happens to run, including the destructor of a
 * random-data library. Owning the state is what makes "same seed, same graph" a
 * promise the analyzer can keep whatever else the process is doing.
 *
 * The draws come from a 32-bit xorshift, computed in integers that never leave
 * 64 bits, so one seed spells the same graph on every runtime peq supports.
 *
 * @visibility parent
 */
final class RandomSource
{
    /**
     * The words names are spelled with.
     *
     * @var list<string>
     */
    private const WORDS = [
        'alias', 'amet', 'animi', 'aut', 'beatae', 'culpa', 'dolor', 'eius',
        'enim', 'error', 'fugit', 'harum', 'illum', 'ipsum', 'iure', 'magni',
        'minus', 'nemo', 'nihil', 'odio', 'omnis', 'porro', 'quia', 'quod',
        'rerum', 'sequi', 'sint', 'soluta', 'tempora', 'ullam', 'velit', 'vero',
        'ad', 'atque', 'commodi', 'dicta', 'earum', 'esse', 'facere', 'fuga',
        'hic', 'id', 'labore', 'modi', 'natus', 'nobis', 'officia', 'quas',
        'ratione', 'saepe', 'sed', 'similique', 'sunt', 'ut', 'vitae', 'voluptas',
    ];

    /**
     * The constant the seed is mixed with, so that seed zero has a state to start from.
     */
    private const MIX = 0x9E3779B9;

    /**
     * The mask keeping the state to 32 bits.
     */
    private const MASK = 0xFFFFFFFF;

    /**
     * The current state of the sequence, never zero.
     */
    private int $state;

    /**
     * @param int $seed The seed the sequence starts from
     */
    public function __construct(int $seed)
    {
        $state = ($seed ^ self::MIX) & self::MASK;
        $this->state = $state === 0 ? self::MIX : $state;
    }

    /**
     * Draws a number between two bounds, both included.
     *
     * @param int $min The smallest number that may be drawn
     * @param int $max The largest number that may be drawn, no smaller than $min
     *
     * @return int The drawn number
     */
    public function numberBetween(int $min, int $max): int
    {
        assert($min <= $max, 'The largest number that may be drawn must be no smaller than the smallest');

        $state = $this->state;
        $state ^= ($state << 13) & self::MASK;
        $state ^= $state >> 17;
        $state ^= ($state << 5) & self::MASK;
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
