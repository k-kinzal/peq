<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Pattern;

/**
 * How many times a pattern may repeat.
 *
 * This is what turns "what does this call" into "what does this reach": `{1,3}` asks
 * for anything up to three steps away without the query having to be written three
 * times. A lower bound of zero matches nothing at all, which makes the two ends of
 * the pattern the same node — the way GQL says it should.
 *
 * The upper bound may be absent, meaning as far as the graph goes. On a graph with
 * cycles that is only finite because a path mode makes it so, which is why GQL
 * allows it only under a restrictor — `TRAIL`, `SIMPLE` or `ACYCLIC`.
 */
final readonly class Quantifier
{
    /**
     * @param int      $least The fewest repetitions that match
     * @param null|int $most  The most that match, or null for as many as the graph allows
     */
    public function __construct(
        public int $least = 0,
        public ?int $most = null,
    ) {
        assert($this->least >= 0, 'A pattern cannot repeat fewer than no times');
        assert($this->most === null || $this->most >= $this->least, 'A pattern cannot repeat fewer times at most than at least');
    }

    /**
     * Returns the quantifier for a pattern written to repeat an exact number of times.
     *
     * @param int $times How many times it repeats
     *
     * @example An exact quantifier has the same bound at both ends
     *     \App\Gql\Syntax\Pattern\Quantifier::exactly(3)->most // => 3
     *
     * @return self The quantifier
     */
    public static function exactly(int $times): self
    {
        return new self($times, $times);
    }

    /**
     * Reports whether a number of repetitions is within the bounds.
     *
     * @param int $times How many repetitions to test
     *
     * @example A repetition inside the bounds is allowed
     *     (new \App\Gql\Syntax\Pattern\Quantifier(1, 3))->allows(2) // => true
     * @example One past the upper bound is not
     *     (new \App\Gql\Syntax\Pattern\Quantifier(1, 3))->allows(4) // => false
     * @example Without an upper bound, nothing is too many
     *     (new \App\Gql\Syntax\Pattern\Quantifier(1))->allows(99) // => true
     *
     * @return bool True when that many repetitions match
     */
    public function allows(int $times): bool
    {
        return $times >= $this->least && ($this->most === null || $times <= $this->most);
    }
}
